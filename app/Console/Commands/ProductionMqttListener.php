<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sensor;
use App\Models\SensorData;
use App\Models\Organization;
use App\Models\Building;
use App\Models\Room;
use App\Services\DoorDetectionService;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class ProductionMqttListener extends Command
{
    protected $signature = 'mqtt:production {--timeout=0 : Timeout in seconds (0 = no timeout)}';
    protected $description = 'PRODUCTION MQTT Listener - Real RuuviTag data from Pi (gw-event/status/+)';

    private MqttClient $mqtt;
    private array $knownSensors = [];

    public function handle(): int
    {
        $this->info('🚀 PRODUCTION MQTT LISTENER - Real RuuviTag Data');
        $mqttHost = config('mqtt.host', '192.168.68.109');
        $mqttPort = config('mqtt.port', 1883);
        
        $this->info("📡 Pi MQTT: {$mqttHost}:{$mqttPort}");
        $this->info('📊 Topic: gw-event/status/+');
        $this->line('=====================================');

        try {
            // Initialize Pi MQTT connection
            $this->mqtt = new MqttClient($mqttHost, $mqttPort, 'ecocomfort_production');
            
            $connectionSettings = (new ConnectionSettings())
                ->setKeepAliveInterval(60)
                ->setLastWillTopic('ecocomfort/status')
                ->setLastWillMessage('offline')
                ->setLastWillQualityOfService(0);

            $this->info('🔌 Connecting to Pi MQTT...');
            $this->mqtt->connect($connectionSettings);
            $this->info('✅ Connected to Pi MQTT successfully');

            // Subscribe to real RuuviTag topic
            $this->mqtt->subscribe('gw-event/status/+', function (string $topic, string $message) {
                $this->processRuuviTagMessage($topic, $message);
            }, 0);

            $this->info('🔔 Subscribed to gw-event/status/+');
            $this->info('👂 Listening for REAL RuuviTag data...');

            $timeout = (int) $this->option('timeout');
            $startTime = time();

            while (true) {
                $this->mqtt->loop(true, true);
                
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    $this->info("⏰ Timeout reached ({$timeout}s)");
                    break;
                }

                // Check every 10 seconds
                if ((time() - $startTime) % 10 == 0) {
                    pcntl_signal_dispatch(); // Handle signals
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Production MQTT failed: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            if (isset($this->mqtt)) {
                $this->mqtt->disconnect();
                $this->info('🔌 Disconnected from Pi MQTT');
            }
        }

        return self::SUCCESS;
    }

    private function processRuuviTagMessage(string $topic, string $message): void
    {
        // Extract source_address from topic: gw-event/status/202481587021839
        if (!preg_match('/gw-event\/status\/(\d+)/', $topic, $matches)) {
            return;
        }

        $sourceAddress = (int) $matches[1];
        $this->line("🏷️  RuuviTag: {$sourceAddress}");

        try {
            // Try JSON first, then binary decode
            $jsonData = json_decode($message, true);
            
            if ($jsonData && isset($jsonData['data'])) {
                // JSON format message
                $this->processJsonMessage($sourceAddress, $jsonData);
            } else {
                // Binary RuuviTag data
                $this->processBinaryMessage($sourceAddress, $message);
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to process RuuviTag {$sourceAddress}: " . $e->getMessage());
            Log::error('RuuviTag message processing error', [
                'source_address' => $sourceAddress,
                'topic' => $topic,
                'message_length' => strlen($message),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function processJsonMessage(int $sourceAddress, array $jsonData): void
    {
        $sensorTypeId = $jsonData['sensor_id'] ?? null;
        $data = $jsonData['data'] ?? [];

        $this->line("📊 JSON format | Type: {$sensorTypeId}");
        $this->processSensorType($sourceAddress, $sensorTypeId, $data, $jsonData);
    }

    private function processBinaryMessage(int $sourceAddress, string $binaryData): void
    {
        $this->line("📡 Binary format");
        
        // Decode RuuviTag binary data (all sensor types at once)
        $data = $this->decodeRuuviTagBinary($binaryData);
        
        if ($data) {
            $this->info("🌡️  Temperature: {$data['temperature']}°C");
            $this->info("💧 Humidity: {$data['humidity']}%");
            if (isset($data['battery_level'])) {
                $this->info("🔋 Battery: {$data['battery_level']}%");
            }
            
            // Store combined data for this sensor
            $this->storeRealSensorData($sourceAddress, $data, 'Complete RuuviTag Data');
        }
    }

    private function decodeRuuviTagBinary(string $binaryData): ?array
    {
        // RuuviTag data format (simplified decoding)
        if (strlen($binaryData) < 20) {
            return null;
        }

        // Basic binary unpacking - adjust based on actual RuuviTag format
        $unpacked = unpack('C*', $binaryData);
        
        if (!$unpacked || count($unpacked) < 20) {
            return null;
        }

        try {
            // RuuviTag format 5 decoding (approximate)
            $temperature = ((($unpacked[3] & 0xFF) << 8) | ($unpacked[4] & 0xFF)) * 0.005;
            $humidity = (($unpacked[5] & 0xFF) << 8 | ($unpacked[6] & 0xFF)) * 0.0025;
            $battery = ($unpacked[19] & 0xFF) * 0.001 + 1.6; // Rough battery estimation

            return [
                'temperature' => round($temperature, 2),
                'humidity' => round($humidity, 2),
                'battery_level' => round(min(100, max(0, ($battery - 1.6) / (3.6 - 1.6) * 100)), 0),
                'timestamp' => now()
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    private function processSensorType(int $sourceAddress, ?int $sensorTypeId, array $data, array $fullMessage): void
    {
        // Skip configuration messages (sensor_id = 0)
        if ($sensorTypeId === 0) {
            $this->line("⚙️  Config message - skipped");
            return;
        }

        // Map sensor types to readable names
        $sensorTypes = [
            112 => 'Temperature',
            114 => 'Humidity', 
            116 => 'Atmospheric Pressure',
            127 => 'Accelerometer',
            142 => 'Battery',
            192 => 'BLE Details',
            193 => 'BLE Count'
        ];

        $typeName = $sensorTypes[$sensorTypeId] ?? "Unknown ({$sensorTypeId})";
        $this->line("📊 {$typeName}");

        // Extract sensor data from the message
        $sensorData = $this->extractSensorData($sensorTypeId, $fullMessage);
        
        if ($sensorData) {
            $this->storeRealSensorData($sourceAddress, $sensorData, $typeName);
        }
    }

    private function extractSensorData(int $sensorTypeId, array $message): ?array
    {
        // Extract timestamp
        $timestamp = isset($message['tx_time_ms_epoch']) 
            ? now()->setTimestamp($message['tx_time_ms_epoch'] / 1000)
            : now();

        // Process different sensor types
        switch ($sensorTypeId) {
            case 112: // Temperature
                $temp = $this->extractTemperature($message);
                if ($temp !== null) {
                    $this->info("🌡️  Temperature: {$temp}°C");
                    return ['temperature' => $temp, 'timestamp' => $timestamp];
                }
                break;
                
            case 114: // Humidity
                $humidity = $this->extractHumidity($message);
                if ($humidity !== null) {
                    $this->info("💧 Humidity: {$humidity}%");
                    return ['humidity' => $humidity, 'timestamp' => $timestamp];
                }
                break;
                
            case 116: // Atmospheric Pressure
                $pressure = $this->extractPressure($message);
                if ($pressure !== null) {
                    $this->info("🌤️  Pressure: {$pressure} hPa");
                    return ['atmospheric_pressure' => $pressure, 'timestamp' => $timestamp];
                }
                break;
                
            case 127: // Accelerometer
                $accel = $this->extractAccelerometer($message);
                if ($accel !== null) {
                    $this->info("📐 Accelerometer: X={$accel['x']}, Y={$accel['y']}, Z={$accel['z']}");
                    
                    // Perform door detection with accelerometer data
                    $doorDetectionResult = $this->performDoorDetection($sourceAddress, $accel);
                    
                    return [
                        'accelerometer_x' => $accel['x'], 
                        'accelerometer_y' => $accel['y'], 
                        'accelerometer_z' => $accel['z'], 
                        'timestamp' => $timestamp,
                        'door_detection' => $doorDetectionResult
                    ];
                }
                break;
                
            case 142: // Battery
                $battery = $this->extractBattery($message);
                if ($battery !== null) {
                    $this->info("🔋 Battery: {$battery}%");
                    return ['battery_level' => $battery, 'timestamp' => $timestamp];
                }
                break;
        }

        return null;
    }

    private function extractTemperature(array $message): ?float
    {
        // Look for temperature in data field or direct value
        if (isset($message['data']['temperature'])) {
            return round($message['data']['temperature'], 2);
        }
        return null;
    }

    private function extractHumidity(array $message): ?float
    {
        if (isset($message['data']['humidity'])) {
            return round($message['data']['humidity'], 2);
        }
        return null;
    }

    private function extractPressure(array $message): ?float
    {
        if (isset($message['data']['pressure'])) {
            return round($message['data']['pressure'], 2);
        }
        return null;
    }

    private function extractAccelerometer(array $message): ?array
    {
        // Check for acceleration data format
        if (isset($message['data']['acceleration'])) {
            $accel = $message['data']['acceleration'];
            return [
                'x' => round($accel['x'] ?? 0, 3),
                'y' => round($accel['y'] ?? 0, 3), 
                'z' => round($accel['z'] ?? 0, 3)
            ];
        }
        
        // Check for Wirepas format (x_axis, y_axis, z_axis)
        if (isset($message['data']['x_axis'], $message['data']['y_axis'], $message['data']['z_axis'])) {
            return [
                'x' => (float) $message['data']['x_axis'],
                'y' => (float) $message['data']['y_axis'],
                'z' => (float) $message['data']['z_axis']
            ];
        }
        
        return null;
    }

    private function extractBattery(array $message): ?int
    {
        if (isset($message['data']['voltage'])) {
            // Convert voltage to percentage (rough estimation)
            $voltage = $message['data']['voltage'];
            $batteryPercent = max(0, min(100, ($voltage - 1.6) / (3.6 - 1.6) * 100));
            return round($batteryPercent);
        }
        return null;
    }

    private function storeRealSensorData(int $sourceAddress, array $data, string $typeName): void
    {
        // Get existing sensor by source_address
        $sensor = $this->getExistingSensor($sourceAddress);
        
        if (!$sensor) {
            $this->warn("⚠️  No sensor found for source_address: {$sourceAddress}");
            return;
        }

        // Create sensor data entry with available data
        $sensorDataArray = [
            'sensor_id' => $sensor->id,
            'timestamp' => $data['timestamp']
        ];

        // Add all available data types
        foreach (['temperature', 'humidity', 'atmospheric_pressure', 'accelerometer_x', 'accelerometer_y', 'accelerometer_z', 'battery_level'] as $field) {
            if (isset($data[$field])) {
                $sensorDataArray[$field] = $data[$field];
            }
        }

        // Add door detection data if available
        if (isset($data['door_detection'])) {
            $doorData = $data['door_detection'];
            $sensorDataArray['door_state'] = $this->convertDoorStateToBoolean($doorData['door_state']);
            $sensorDataArray['door_state_certainty'] = $doorData['certainty'];
            $sensorDataArray['needs_confirmation'] = $doorData['needs_confirmation'] ?? false;
            
            $this->info("🚪 Door state stored: {$doorData['door_state']} ({$doorData['certainty']})");
        }

        // Update sensor status if battery data available
        if (isset($data['battery_level'])) {
            $sensor->update([
                'battery_level' => $data['battery_level'],
                'last_seen_at' => $data['timestamp'],
                'is_active' => true
            ]);
        } else {
            // At least update last_seen_at
            $sensor->update([
                'last_seen_at' => $data['timestamp'],
                'is_active' => true
            ]);
        }

        SensorData::create($sensorDataArray);

        $this->info("✅ Stored {$typeName} for sensor: {$sensor->name}");
    }

    private function getExistingSensor(int $sourceAddress): ?Sensor
    {
        // Check cache first
        if (isset($this->knownSensors[$sourceAddress])) {
            return $this->knownSensors[$sourceAddress];
        }

        // Find existing sensor by source_address
        $sensor = Sensor::where('source_address', $sourceAddress)->first();
        
        if ($sensor) {
            // Cache for future use
            $this->knownSensors[$sourceAddress] = $sensor;
        }

        return $sensor;
    }

    private function convertDoorStateToBoolean(string $doorState): bool
    {
        // Convert door state to boolean for database
        // false = closed, true = opened
        return match($doorState) {
            'closed' => false,
            'opened', 'probably_opened' => true,
            default => false // Default to closed if unknown (conservative approach)
        };
    }

    private function performDoorDetection(int $sourceAddress, array $accel): array
    {
        // Get sensor for door detection
        $sensor = $this->getExistingSensor($sourceAddress);
        if (!$sensor) {
            return [
                'door_state' => 'unknown',
                'certainty' => 'UNCERTAIN',
                'confidence' => 0.0,
                'error' => 'Sensor not found'
            ];
        }

        try {
            // Convert accelerometer data to normalized format (divide by 64 for Wirepas sensors)
            $normalizedAccel = [
                'x' => $accel['x'] / 64.0,
                'y' => $accel['y'] / 64.0,
                'z' => $accel['z'] / 64.0
            ];

            // Use DoorDetectionService for detection
            $doorDetectionService = new DoorDetectionService();
            $result = $doorDetectionService->detectDoorState(
                $normalizedAccel['x'],
                $normalizedAccel['y'],
                $normalizedAccel['z'],
                $sensor->id
            );

            $this->info("🚪 Door Detection: {$result['door_state']} ({$result['certainty']}, {$result['confidence']}%)");

            return $result;

        } catch (\Exception $e) {
            Log::error("Door detection failed for sensor {$sourceAddress}", [
                'error' => $e->getMessage(),
                'accel_data' => $accel
            ]);

            return [
                'door_state' => 'unknown',
                'certainty' => 'UNCERTAIN',
                'confidence' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }
}
