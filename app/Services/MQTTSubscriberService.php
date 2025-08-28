<?php

namespace App\Services;

use App\Models\Sensor;
use App\Models\SensorData;
use App\Models\Organization;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MQTTSubscriberService
{
    private MqttClient $mqtt;
    private ?MqttClient $sourceMqtt = null;
    private ?MqttClient $destinationMqtt = null;
    private bool $bridgeMode = false;
    private array $knownSensors = [];
    private DoorDetectionService $doorDetectionService;
    private EnergyCalculatorService $energyCalculatorService;
    private NotificationService $notificationService;
    
    public function __construct(
        DoorDetectionService $doorDetectionService,
        EnergyCalculatorService $energyCalculatorService,
        NotificationService $notificationService
    ) {
        $this->doorDetectionService = $doorDetectionService;
        $this->energyCalculatorService = $energyCalculatorService;
        $this->notificationService = $notificationService;
        
        $this->initializeMqttClient();
    }
    
    private function initializeMqttClient(): void
    {
        $this->mqtt = new MqttClient(
            config('mqtt.host', 'localhost'),
            config('mqtt.port', 1883),
            config('mqtt.client_id', 'ecocomfort_laravel'),
            MqttClient::MQTT_3_1_1
        );
        
        Log::info('MQTT client initialized', [
            'host' => config('mqtt.host'),
            'port' => config('mqtt.port'),
            'client_id' => config('mqtt.client_id'),
            'use_tls' => config('mqtt.use_tls'),
            'username' => config('mqtt.username') ? 'SET' : 'NOT_SET'
        ]);
    }
    
    public function connect(): void
    {
        try {
            $connectionSettings = (new ConnectionSettings)
                ->setKeepAliveInterval(config('mqtt.connection.keep_alive_interval', 60))
                ->setUseTls(config('mqtt.use_tls', false))
                ->setConnectTimeout(config('mqtt.connection.connect_timeout', 5))
                ->setSocketTimeout(config('mqtt.connection.socket_timeout', 5))
                ->setResendTimeout(config('mqtt.connection.resend_timeout', 10))
                ->setUsername(config('mqtt.username'))
                ->setPassword(config('mqtt.password'));
            
            if (config('mqtt.use_tls')) {
                // Configure TLS for HiveMQ Cloud (insecure mode like --insecure)
                $connectionSettings = $connectionSettings
                    ->setTlsSelfSignedAllowed(true)
                    ->setTlsVerifyPeer(false)
                    ->setTlsVerifyPeerName(false);
            }
            
            Log::info('Attempting MQTT connection...', [
                'host' => config('mqtt.host'),
                'port' => config('mqtt.port'),
                'use_tls' => config('mqtt.use_tls'),
                'clean_session' => config('mqtt.connection.clean_session', true)
            ]);
            
            $this->mqtt->connect(
                $connectionSettings, 
                config('mqtt.connection.clean_session', true)
            );
            
            Log::info('🚀 MQTT connected successfully to HiveMQ Cloud!');
            
        } catch (\Exception $e) {
            Log::error('❌ MQTT connection failed: ' . $e->getMessage(), [
                'host' => config('mqtt.host'),
                'port' => config('mqtt.port'),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    public function subscribe(): void
    {
        // Subscribe to RuuviTag data topic with wildcard pattern
        $this->mqtt->subscribe(
            'pws-packet/202481601481463/+/+',
            function (string $topic, string $message, bool $retained, array $matchedWildcards) {
                $this->handleRuuviTagMessage($topic, $message);
            },
            0
        );
        
        Log::info('MQTT subscriptions registered for RuuviTag data');
    }
    
    private function handleRuuviTagMessage(string $topic, string $message): void
    {
        try {
            // Parse topic: pws-packet/202481601481463/422801533/112
            $topicParts = explode('/', $topic);
            if (count($topicParts) !== 4 || $topicParts[0] !== 'pws-packet') {
                return; // Skip non-RuuviTag messages
            }
            
            $sourceAddress = (int)$topicParts[2];
            $sensorTypeId = (int)$topicParts[3];
            
            $data = json_decode($message, true);
            if (!$data) {
                return;
            }
            
            // Create RuuviTag format for existing handlers
            $ruuviData = [
                'sensor_id' => $sensorTypeId,
                'source_address' => $sourceAddress,
                'data' => $data
            ];
            
            $ruuviMessage = json_encode($ruuviData);
            
            // Route to appropriate handler based on sensor type
            switch ($sensorTypeId) {
                case 112: // Temperature
                    $this->handleTemperatureMessage($ruuviMessage);
                    break;
                case 114: // Humidity
                    $this->handleHumidityMessage($ruuviMessage);
                    break;
                case 127: // Accelerometer
                    $this->handleAccelerometerMessage($ruuviMessage);
                    break;
                default:
                    // Skip unknown sensor types (116, 142, 192, 193)
                    break;
            }
            
        } catch (\Exception $e) {
            Log::error('Error handling RuuviTag message: ' . $e->getMessage(), [
                'topic' => $topic,
                'message' => $message,
                'exception' => $e
            ]);
        }
    }
    
    private function handleTemperatureMessage(string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            // Support RuuviTag format: {"sensor_id": 112, "source_address": 422801533, "data": {"temperature": 26.27}}
            if (!$data) {
                Log::warning('Invalid JSON message format', ['message' => $message]);
                return;
            }
            
            // Parse RuuviTag format (handle nested data structure)
            $sensorId = $data['sensor_id'] ?? null;
            $sourceAddress = $data['source_address'] ?? null;
            // Handle nested data: data.data.temperature or data.temperature
            $temperature = $data['data']['data']['temperature'] ?? $data['data']['temperature'] ?? $data['temperature'] ?? null;
            
            if (!$sensorId || !$sourceAddress || $temperature === null) {
                Log::warning('Invalid temperature message format', ['message' => $message]);
                return;
            }
            
            $sensor = $this->getSensorBySourceAddress($sourceAddress, $sensorId);
            if (!$sensor) {
                Log::warning('Unknown sensor source_address - sensor must be configured first', ['sensor_id' => $sensorId, 'source_address' => $sourceAddress]);
                return;
            }
            
            // Calibrate temperature
            $temperature = $sensor->calibrateTemperature($temperature);
            
            // Get or create sensor data record
            $sensorData = $this->getOrCreateSensorData($sensor->id);
            $sensorData->temperature = $temperature;
            $sensorData->save();
            
            // Update sensor last seen
            $sensor->updateLastSeen();
            
            // Update battery level if provided
            if (isset($data['battery'])) {
                $sensor->updateBatteryLevel($data['battery']);
            }
            
            // Check for temperature alerts
            $this->checkTemperatureAlerts($sensor, $temperature);
            
            // Clear cache
            Cache::forget("sensor_{$sensor->id}_latest_data");
            
            // Broadcast via WebSocket
            $this->broadcastSensorUpdate($sensor, $sensorData);
            
        } catch (\Exception $e) {
            Log::error('Error handling temperature message: ' . $e->getMessage(), [
                'message' => $message,
                'exception' => $e
            ]);
        }
    }
    
    private function handleHumidityMessage(string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            // Support RuuviTag format: {"sensor_id": 114, "source_address": 422801533, "data": {"humidity": 62.93}}
            if (!$data) {
                Log::warning('Invalid JSON message format', ['message' => $message]);
                return;
            }
            
            // Parse RuuviTag format (handle nested data structure)
            $sensorId = $data['sensor_id'] ?? null;
            $sourceAddress = $data['source_address'] ?? null;
            // Handle nested data: data.data.humidity or data.humidity
            $humidity = $data['data']['data']['humidity'] ?? $data['data']['humidity'] ?? $data['humidity'] ?? null;
            
            if (!$sensorId || !$sourceAddress || $humidity === null) {
                Log::warning('Invalid humidity message format', ['message' => $message]);
                return;
            }
            
            $sensor = $this->getSensorBySourceAddress($sourceAddress, $sensorId);
            if (!$sensor) {
                Log::warning('Unknown sensor source_address - sensor must be configured first', ['sensor_id' => $sensorId, 'source_address' => $sourceAddress]);
                return;
            }
            
            // Calibrate humidity
            $humidity = $sensor->calibrateHumidity($humidity);
            
            // Get or create sensor data record
            $sensorData = $this->getOrCreateSensorData($sensor->id);
            $sensorData->humidity = $humidity;
            $sensorData->save();
            
            // Update sensor last seen
            $sensor->updateLastSeen();
            
            // Check for humidity alerts
            $this->checkHumidityAlerts($sensor, $humidity);
            
            // Clear cache
            Cache::forget("sensor_{$sensor->id}_latest_data");
            
            // Broadcast via WebSocket
            $this->broadcastSensorUpdate($sensor, $sensorData);
            
        } catch (\Exception $e) {
            Log::error('Error handling humidity message: ' . $e->getMessage(), [
                'message' => $message,
                'exception' => $e
            ]);
        }
    }
    
    private function handleAccelerometerMessage(string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            // Support RuuviTag format: {"sensor_id": 127, "source_address": 422801533, "data": {"state": "stop-moving", "x_axis": 3, "y_axis": -1, "z_axis": 61}}
            if (!$data) {
                Log::warning('Invalid JSON message format', ['message' => $message]);
                return;
            }
            
            // Parse RuuviTag format (handle nested data structure)
            $sensorId = $data['sensor_id'] ?? null;
            $sourceAddress = $data['source_address'] ?? null;
            // Handle nested accelerometer data: data.data or data
            $accelerometerData = $data['data']['data'] ?? $data['data'] ?? [];
            
            if (!$sensorId || !$sourceAddress || empty($accelerometerData)) {
                Log::warning('Invalid accelerometer message format', ['message' => $message]);
                return;
            }
            
            $sensor = $this->getSensorBySourceAddress($sourceAddress, $sensorId);
            if (!$sensor) {
                Log::warning('Unknown sensor source_address - sensor must be configured first', ['sensor_id' => $sensorId, 'source_address' => $sourceAddress]);
                return;
            }
            
            // Get or create sensor data record
            $sensorData = $this->getOrCreateSensorData($sensor->id);
            
            // RuuviTag accelerometer format: x_axis, y_axis, z_axis
            $sensorData->acceleration_x = $accelerometerData['x_axis'] ?? $accelerometerData['x'] ?? 0;
            $sensorData->acceleration_y = $accelerometerData['y_axis'] ?? $accelerometerData['y'] ?? 0;
            $sensorData->acceleration_z = $accelerometerData['z_axis'] ?? $accelerometerData['z'] ?? 0;
            
            // Store RuuviTag movement state if available
            $movementState = $accelerometerData['state'] ?? null;
            if ($movementState) {
                $sensorData->movement_state = $movementState;
            }
            
            // Detect door state using Kalman filter
            $doorDetection = $this->doorDetectionService->detectDoorState(
                $sensorData->acceleration_x,
                $sensorData->acceleration_y,
                $sensorData->acceleration_z,
                $sensor->id
            );
            
            $previousDoorState = $sensorData->door_state;
            
            // Extract boolean value from detection result
            if (is_array($doorDetection) && isset($doorDetection['door_state'])) {
                $sensorData->door_state = $doorDetection['door_state'] === 'opened';
                
                // Log detailed detection info for debugging
                Log::info('Door detection result', [
                    'sensor_id' => $sensor->id,
                    'state' => $doorDetection['door_state'],
                    'confidence' => $doorDetection['confidence'] ?? null,
                    'angle' => $doorDetection['angle'] ?? null,
                    'magnitude' => $doorDetection['magnitude'] ?? null,
                    'accel' => ['x' => $sensorData->acceleration_x, 'y' => $sensorData->acceleration_y, 'z' => $sensorData->acceleration_z]
                ]);
            } else {
                // Fallback: simple boolean detection
                $sensorData->door_state = (bool) $doorDetection;
            }
            
            // Calculate energy loss if door is open
            if ($sensorData->door_state && $sensorData->temperature !== null) {
                $outdoorTemp = $this->getOutdoorTemperature();
                $sensorData->energy_loss_watts = $this->energyCalculatorService->calculateEnergyLoss(
                    $sensorData->temperature,
                    $outdoorTemp,
                    $sensor->room->surface_m2
                );
            } else {
                $sensorData->energy_loss_watts = 0;
            }
            
            $sensorData->save();
            
            // Update sensor last seen
            $sensor->updateLastSeen();
            
            // Check for door state change alerts
            if ($previousDoorState !== $doorState) {
                $this->handleDoorStateChange($sensor, $doorState, $sensorData);
            }
            
            // Clear cache
            Cache::forget("sensor_{$sensor->id}_latest_data");
            
            // Broadcast via WebSocket
            $this->broadcastSensorUpdate($sensor, $sensorData);
            
        } catch (\Exception $e) {
            Log::error('Error handling accelerometer message: ' . $e->getMessage(), [
                'message' => $message,
                'exception' => $e
            ]);
        }
    }
    
    private function getSensorByMac(string $mac): ?Sensor
    {
        return Cache::remember(
            "sensor_mac_{$mac}",
            now()->addHours(1),
            fn() => Sensor::where('mac_address', $mac)->first()
        );
    }
    
    private function getSensorBySourceAddress(int $sourceAddress, int $sensorId): ?Sensor
    {
        // Find sensor by source_address only (ignore sensor_id since one RuuviTag = multiple internal sensors)
        return Cache::remember(
            "sensor_source_{$sourceAddress}",
            now()->addHours(1),
            fn() => Sensor::where('source_address', $sourceAddress)
                ->first()
        );
    }
    
    private function createSensorFromRuuviTag(int $sensorId, int $sourceAddress): ?Sensor
    {
        try {
            // Get default organization and room
            $organization = \App\Models\Organization::first();
            if (!$organization) {
                Log::error('No organization found for auto-creating sensor');
                return null;
            }
            
            // Get or create default building and room
            $building = $organization->buildings()->first();
            if (!$building) {
                $building = $organization->buildings()->create([
                    'name' => 'Bâtiment Principal',
                    'address' => 'Adresse par défaut',
                    'floors' => 1,
                    'total_surface_m2' => 100,
                ]);
            }
            
            $room = $building->rooms()->first();
            if (!$room) {
                $room = $building->rooms()->create([
                    'name' => 'Salle par défaut',
                    'floor' => 1,
                    'surface_m2' => 25,
                    'target_temperature' => 22.0,
                    'target_humidity' => 50.0,
                ]);
            }
            
            // Determine sensor type
            $sensorTypeName = match($sensorId) {
                112 => 'Température',
                114 => 'Humidité',
                127 => 'Mouvement/Accéléromètre',
                default => "Capteur {$sensorId}",
            };
            
            // Create sensor
            $sensor = Sensor::create([
                'room_id' => $room->id,
                'name' => "RuuviTag {$sensorTypeName}",
                'type' => 'ruuvitag',
                'position' => 'wall',
                'source_address' => $sourceAddress,
                'sensor_type_id' => $sensorId,
                'is_active' => true,
                'battery_level' => 100,
                'last_seen_at' => now(),
                'temperature_offset' => 0.0,
                'humidity_offset' => 0.0,
            ]);
            
            // Clear cache
            Cache::forget("sensor_source_{$sourceAddress}_{$sensorId}");
            
            Log::info("Auto-created RuuviTag sensor", [
                'sensor_id' => $sensor->id,
                'sensor_type_id' => $sensorId,
                'source_address' => $sourceAddress,
                'room' => $room->name
            ]);
            
            return $sensor;
            
        } catch (\Exception $e) {
            Log::error('Failed to create RuuviTag sensor: ' . $e->getMessage(), [
                'sensor_id' => $sensorId,
                'source_address' => $sourceAddress,
                'exception' => $e
            ]);
            return null;
        }
    }
    
    private function getOrCreateSensorData(string $sensorId): SensorData
    {
        // Try to get recent sensor data (within last 5 minutes to allow RuuviTag sensor aggregation)
        $recentData = SensorData::where('sensor_id', $sensorId)
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->orderBy('timestamp', 'desc')
            ->first();
        
        if ($recentData) {
            // Update timestamp to keep data fresh
            $recentData->timestamp = now();
            return $recentData;
        }
        
        // Create new sensor data record
        return SensorData::create([
            'sensor_id' => $sensorId,
            'timestamp' => now(),
        ]);
    }
    
    private function checkTemperatureAlerts(Sensor $sensor, float $temperature): void
    {
        $room = $sensor->room;
        $targetTemp = $room->target_temperature;
        $threshold = 3; // degrees
        
        if ($temperature > $targetTemp + $threshold) {
            $this->notificationService->sendTemperatureAlert($sensor, $temperature, 'high');
        } elseif ($temperature < $targetTemp - $threshold) {
            $this->notificationService->sendTemperatureAlert($sensor, $temperature, 'low');
        }
    }
    
    private function checkHumidityAlerts(Sensor $sensor, float $humidity): void
    {
        $room = $sensor->room;
        $targetHumidity = $room->target_humidity;
        $threshold = 15; // percentage
        
        if ($humidity > $targetHumidity + $threshold) {
            $this->notificationService->sendHumidityAlert($sensor, $humidity, 'high');
        } elseif ($humidity < $targetHumidity - $threshold) {
            $this->notificationService->sendHumidityAlert($sensor, $humidity, 'low');
        }
    }
    
    private function handleDoorStateChange(Sensor $sensor, bool $isOpen, SensorData $sensorData): void
    {
        if ($isOpen) {
            $this->notificationService->sendDoorOpenAlert($sensor, $sensorData->energy_loss_watts ?? 0);
        } else {
            // Door closed - reward user if they closed it quickly
            $this->notificationService->sendDoorClosedNotification($sensor);
        }
    }
    
    private function getOutdoorTemperature(): float
    {
        // This could be fetched from a weather API or outdoor sensor
        // For now, return a default value
        return Cache::remember('outdoor_temperature', now()->addHours(1), function () {
            // TODO: Implement weather API integration
            return 10.0; // Default outdoor temperature
        });
    }
    
    private function broadcastSensorUpdate(Sensor $sensor, SensorData $data): void
    {
        try {
            broadcast(new \App\Events\SensorDataUpdated($sensor, $data));
        } catch (\Exception $e) {
            Log::error('Failed to broadcast sensor update: ' . $e->getMessage());
        }
    }
    
    /**
     * Bridge Mode: Connect to Pi MQTT and republish to HiveMQ Cloud
     */
    public function bridgeMode(): void
    {
        $this->bridgeMode = true;
        
        Log::info('🌉 Starting MQTT Bridge Mode');
        Log::info('📥 Source: Pi MQTT (192.168.1.216:1883)');
        Log::info('📤 Destination: HiveMQ Cloud (' . config('mqtt.host') . ')');
        
        try {
            // Initialize source connection (Pi local MQTT)
            $this->initializeSourceConnection();
            
            // Initialize destination connection (HiveMQ Cloud)
            $this->initializeDestinationConnection();
            
            // Connect both
            $this->connectSourceAndDestination();
            
            // Subscribe to topics on source
            $this->subscribeSourceTopics();
            
            Log::info('🚀 Bridge active - listening for messages from Pi...');
            
            // Start listening loop
            $this->bridgeLoop();
            
        } catch (\Exception $e) {
            Log::error('❌ Bridge mode error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function initializeSourceConnection(): void
    {
        $this->sourceMqtt = new MqttClient(
            '192.168.1.216',
            1883,
            'ecocomfort_bridge_source',
            MqttClient::MQTT_3_1_1
        );
        
        Log::info('📥 Source MQTT client initialized');
    }
    
    private function initializeDestinationConnection(): void
    {
        $this->destinationMqtt = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            'ecocomfort_bridge_dest',
            MqttClient::MQTT_3_1_1
        );
        
        Log::info('📤 Destination MQTT client initialized');
    }
    
    private function connectSourceAndDestination(): void
    {
        // Connect to Pi (source)
        $sourceSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(60)
            ->setUseTls(false)
            ->setConnectTimeout(5)
            ->setSocketTimeout(5)
            ->setUsername('pi')
            ->setPassword('wirepass123');
            
        $this->sourceMqtt->connect($sourceSettings, true);
        Log::info('✅ Connected to Pi MQTT broker');
        
        // Connect to HiveMQ (destination) - reuse existing config
        $destSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(config('mqtt.connection.keep_alive_interval', 60))
            ->setUseTls(config('mqtt.use_tls', false))
            ->setConnectTimeout(config('mqtt.connection.connect_timeout', 5))
            ->setSocketTimeout(config('mqtt.connection.socket_timeout', 5))
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'));
            
        if (config('mqtt.use_tls')) {
            $destSettings = $destSettings
                ->setTlsSelfSignedAllowed(true)
                ->setTlsVerifyPeer(false)
                ->setTlsVerifyPeerName(false);
        }
        
        $this->destinationMqtt->connect($destSettings, true);
        Log::info('✅ Connected to HiveMQ Cloud broker');
    }
    
    private function subscribeSourceTopics(): void
    {
        // Subscribe to REAL RuuviTag topics on Pi MQTT (not 112/114/127 but gw-event/status/+)
        $topics = ['gw-event/status/+'];
        
        foreach ($topics as $topic) {
            $this->sourceMqtt->subscribe(
                $topic,
                function (string $topic, string $message, bool $retained, array $matchedWildcards) {
                    $this->handleBridgeMessage($topic, $message);
                },
                0
            );
            Log::info("🔔 Subscribed to Pi topic: {$topic}");
        }
    }
    
    /**
     * Handle message from Pi and republish to HiveMQ
     */
    public function handleBridgeMessage(string $topic, string $message): void
    {
        try {
            Log::debug("📥 Bridge received from Pi", ['topic' => $topic, 'message' => $message]);
            
            // Extract sensor ID from topic: gw-event/status/202481587021839
            if (!preg_match('/gw-event\/status\/(\d+)/', $topic, $matches)) {
                Log::warning('Invalid Pi topic format', ['topic' => $topic]);
                return;
            }
            
            $sensorId = $matches[1];
            
            // Decode RuuviTag binary data (same as ProductionMqttListener.php)
            $decodedData = $this->decodeRuuviTagData($message);
            if (!$decodedData) {
                Log::warning('Failed to decode RuuviTag data', ['sensor_id' => $sensorId, 'message_length' => strlen($message)]);
                return;
            }
            
            // Add sensor metadata
            $decodedData['sensor_id'] = $sensorId;
            $decodedData['source_address'] = $sensorId;
            
            // Determine destination topics based on sensor ID (map to 112/114/127)
            $destTopics = $this->getDestinationTopics($sensorId, $decodedData);
            
            foreach ($destTopics as $destTopic => $payload) {
                // Republish to HiveMQ Cloud
                $this->destinationMqtt->publish($destTopic, json_encode($payload), 0);
                
                Log::info("🌉 Bridged RuuviTag data", [
                    'sensor_id' => $sensorId,
                    'from_topic' => $topic,
                    'to_topic' => $destTopic,
                    'temperature' => $decodedData['temperature'] ?? 'N/A',
                    'humidity' => $decodedData['humidity'] ?? 'N/A',
                    'battery' => $decodedData['battery'] ?? 'N/A'
                ]);
            }
            
            // DUAL ACTION: Also store in database for frontend access
            $this->storeRealSensorData($sensorId, $decodedData);
            
            Log::info("📊 Bridge DUAL: HiveMQ + Database storage completed", [
                'sensor_id' => $sensorId,
                'temperature' => $decodedData['temperature'],
                'humidity' => $decodedData['humidity'],
                'battery' => $decodedData['battery']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error bridging RuuviTag message', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'message_length' => strlen($message)
            ]);
        }
    }
    
    private function decodeRuuviTagData(string $binaryData): ?array
    {
        // RuuviTag data format decoding (same as ProductionMqttListener.php)
        if (strlen($binaryData) < 20) {
            return null;
        }

        $unpacked = unpack('C*', $binaryData);
        
        if (!$unpacked || count($unpacked) < 20) {
            return null;
        }

        try {
            // RuuviTag format 5 decoding
            $temperature = ((($unpacked[3] & 0xFF) << 8) | ($unpacked[4] & 0xFF)) * 0.005;
            $humidity = (($unpacked[5] & 0xFF) << 8 | ($unpacked[6] & 0xFF)) * 0.0025;
            $battery = ($unpacked[19] & 0xFF) * 0.001 + 1.6;

            return [
                'temperature' => round($temperature, 2),
                'humidity' => round($humidity, 2),
                'battery' => round(min(100, max(0, ($battery - 1.6) / (3.6 - 1.6) * 100)), 0),
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getDestinationTopics(string $sensorId, array $data): array
    {
        // Map sensor to appropriate topics
        $topics = [];
        
        // Always publish temperature data to topic 112
        if (isset($data['temperature'])) {
            $topics['112'] = [
                'sensor_id' => $sensorId,
                'source_address' => $sensorId,
                'data' => ['temperature' => $data['temperature']],
                'temperature' => $data['temperature'],
                'timestamp' => $data['timestamp']
            ];
        }
        
        // Always publish humidity data to topic 114
        if (isset($data['humidity'])) {
            $topics['114'] = [
                'sensor_id' => $sensorId,
                'source_address' => $sensorId,
                'data' => ['humidity' => $data['humidity']],
                'humidity' => $data['humidity'],
                'timestamp' => $data['timestamp']
            ];
        }
        
        // Publish battery/movement data to topic 127
        $topics['127'] = [
            'sensor_id' => $sensorId,
            'source_address' => $sensorId,
            'data' => [
                'battery' => $data['battery'],
                'movement' => 'active' // All RuuviTag are movement sensors
            ],
            'battery' => $data['battery'],
            'timestamp' => $data['timestamp']
        ];
        
        return $topics;
    }

    private function getDataType(string $topic): string
    {
        return match($topic) {
            '112' => 'temperature',
            '114' => 'humidity', 
            '127' => 'movement',
            default => 'unknown'
        };
    }
    
    /**
     * Store real sensor data in database (same as ProductionMqttListener)
     */
    private function storeRealSensorData(string $ruuviTagId, array $data): void
    {
        try {
            // Get or create sensor based on real RuuviTag ID
            $sensor = $this->getOrCreateSensor($ruuviTagId);
            
            if (!$sensor) {
                Log::warning("⚠️ No sensor found/created for RuuviTag: {$ruuviTagId}");
                return;
            }

            // Store real sensor data
            SensorData::create([
                'sensor_id' => $sensor->id,
                'temperature' => $data['temperature'],
                'humidity' => $data['humidity'],
                'timestamp' => $data['timestamp'],
                'battery_level' => $data['battery']
            ]);

            // Update sensor status
            $sensor->update([
                'battery_level' => $data['battery'],
                'last_seen_at' => $data['timestamp'],
                'is_active' => true
            ]);

            Log::info("✅ Bridge stored real data for sensor: {$sensor->name}");
            
        } catch (\Exception $e) {
            Log::error("❌ Bridge storage failed for RuuviTag {$ruuviTagId}: " . $e->getMessage());
        }
    }

    private function getOrCreateSensor(string $ruuviTagId): ?Sensor
    {
        try {
            // Check cache first
            if (isset($this->knownSensors[$ruuviTagId])) {
                return $this->knownSensors[$ruuviTagId];
            }

            // Try to find existing sensor by source_address
            $sensor = Sensor::where('source_address', $ruuviTagId)->first();
            
            if (!$sensor) {
                // Auto-create sensor if organization exists
                $org = Organization::first();
                if (!$org) {
                    Log::error("❌ No organization found - cannot create sensor");
                    return null;
                }

                // Create default building/room if needed
                $building = Building::firstOrCreate([
                    'organization_id' => $org->id,
                    'name' => 'Auto-Detected Building'
                ], [
                    'address' => 'Auto-created from RuuviTag data'
                ]);

                $room = Room::firstOrCreate([
                    'building_id' => $building->id,
                    'name' => 'Auto-Detected Room'
                ], [
                    'floor' => 0,
                    'surface_m2' => 20
                ]);

                // Create sensor for this RuuviTag
                $sensor = Sensor::create([
                    'room_id' => $room->id,
                    'source_address' => $ruuviTagId,
                    'name' => "RuuviTag {$ruuviTagId}",
                    'type' => 'ruuvitag',
                    'position' => 'wall',
                    'is_active' => true,
                    'battery_level' => 100
                ]);

                Log::info("✅ Bridge auto-created sensor: {$sensor->name}");
            }

            // Cache it
            $this->knownSensors[$ruuviTagId] = $sensor;
            
            return $sensor;
            
        } catch (\Exception $e) {
            Log::error("❌ Bridge sensor creation failed for {$ruuviTagId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Process message locally for immediate database update
     */
    private function processMessageLocally(string $topic, string $message): void
    {
        try {
            // Reuse existing message handling logic
            switch ($topic) {
                case '112':
                    $this->handleTemperatureMessage($message);
                    break;
                case '114':
                    $this->handleHumidityMessage($message);
                    break;
                case '127':
                    $this->handleAccelerometerMessage($message);
                    break;
            }
        } catch (\Exception $e) {
            Log::warning('Error processing message locally in bridge mode', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function bridgeLoop(): void
    {
        // Use a simple alternating loop to handle both connections
        $maxIterations = 100;
        $iterations = 0;
        
        while (true) {
            try {
                // Process source messages (Pi)
                $this->sourceMqtt->loop(false, true, 1);
                
                // Small delay to prevent CPU overload
                usleep(50000); // 50ms
                
                // Periodic status check
                $iterations++;
                if ($iterations >= $maxIterations) {
                    Log::debug('🌉 Bridge active - processed ' . $maxIterations . ' iterations');
                    $iterations = 0;
                }
                
            } catch (\Exception $e) {
                Log::error('Error in bridge loop: ' . $e->getMessage());
                
                // Try to reconnect
                sleep(5);
                try {
                    $this->connectSourceAndDestination();
                    $this->subscribeSourceTopics();
                    Log::info('🔄 Bridge reconnected successfully');
                } catch (\Exception $reconnectError) {
                    Log::error('Failed to reconnect bridge: ' . $reconnectError->getMessage());
                    throw $reconnectError;
                }
            }
        }
    }
    
    public function disconnectBridge(): void
    {
        if ($this->sourceMqtt) {
            $this->sourceMqtt->disconnect();
            Log::info('📥 Disconnected from Pi MQTT');
        }
        
        if ($this->destinationMqtt) {
            $this->destinationMqtt->disconnect();
            Log::info('📤 Disconnected from HiveMQ Cloud');
        }
        
        Log::info('🌉 Bridge mode stopped');
    }
    
    public function listen(): void
    {
        try {
            // Use non-blocking loop to prevent socket timeouts
            $this->mqtt->loop(false, true);
        } catch (\Exception $e) {
            Log::error('MQTT loop error: ' . $e->getMessage());
            
            // Try to reconnect if socket failed
            if (str_contains($e->getMessage(), 'socket failed') || str_contains($e->getMessage(), 'closed')) {
                Log::info('Attempting MQTT reconnection...');
                try {
                    $this->reconnect();
                } catch (\Exception $reconnectError) {
                    Log::error('MQTT reconnection failed: ' . $reconnectError->getMessage());
                    throw $reconnectError;
                }
            } else {
                throw $e;
            }
        }
    }
    
    private function reconnect(): void
    {
        try {
            // Disconnect first
            if ($this->mqtt) {
                try {
                    $this->mqtt->disconnect();
                } catch (\Exception $e) {
                    // Ignore disconnect errors
                }
            }
            
            // Wait a bit before reconnecting
            sleep(2);
            
            // Reinitialize MQTT client
            $this->initializeMqttClient();
            
            // Reconnect
            $this->connect();
            
            // Resubscribe to topics
            $this->subscribe();
            
            Log::info('🔄 MQTT reconnected successfully');
            
        } catch (\Exception $e) {
            Log::error('MQTT reconnection failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function disconnect(): void
    {
        $this->mqtt->disconnect();
        Log::info('MQTT disconnected');
    }
}