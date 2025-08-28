<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sensor;
use App\Models\SensorData;
use App\Models\Organization;
use App\Models\Building;
use App\Models\Room;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class CorrectRuuviTagListener extends Command
{
    protected $signature = 'mqtt:correct {--timeout=0 : Timeout in seconds (0 = no timeout)}';
    protected $description = 'CORRECT RuuviTag Listener - Parse structured JSON data from single RuuviTag';

    private MqttClient $mqtt;
    private array $ruuvitagSensors = []; // Cache par source_address

    public function handle(): int
    {
        $this->info('🎯 CORRECT RUUVITAG LISTENER - Structured JSON Data');
        $this->info('📡 Pi MQTT: 192.168.1.216:1883');
        $this->info('📊 Format: Single RuuviTag with multiple sensor types');
        $this->line('=============================================');

        try {
            // Initialize Pi MQTT connection
            $this->mqtt = new MqttClient('192.168.1.216', 1883, 'ecocomfort_correct');
            
            $connectionSettings = (new ConnectionSettings())
                ->setKeepAliveInterval(60)
                ->setLastWillTopic('ecocomfort/status')
                ->setLastWillMessage('offline')
                ->setLastWillQualityOfService(0);

            $this->info('🔌 Connecting to Pi MQTT...');
            $this->mqtt->connect($connectionSettings);
            $this->info('✅ Connected to Pi MQTT successfully');

            // Subscribe to RuuviTag topic with JSON data
            $this->mqtt->subscribe('gw-event/status/+', function (string $topic, string $message) {
                $this->processStructuredMessage($topic, $message);
            }, 0);

            $this->info('🔔 Subscribed to gw-event/status/+');
            $this->info('👂 Listening for STRUCTURED RuuviTag data...');

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
                    pcntl_signal_dispatch();
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Correct MQTT failed: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            if (isset($this->mqtt)) {
                $this->mqtt->disconnect();
                $this->info('🔌 Disconnected from Pi MQTT');
            }
        }

        return self::SUCCESS;
    }

    private function processStructuredMessage(string $topic, string $message): void
    {
        try {
            // Try to decode as JSON first
            $jsonData = json_decode($message, true);
            
            if (!$jsonData || !isset($jsonData['source_address'])) {
                // Not JSON or missing source_address, skip
                return;
            }

            $sourceAddress = $jsonData['source_address'];
            $sensorTypeId = $jsonData['sensor_id'] ?? null;
            $data = $jsonData['data'] ?? [];

            $this->line("🏷️  RuuviTag: {$sourceAddress} | Type: {$sensorTypeId}");

            // Process different sensor types
            $this->processSensorType($sourceAddress, $sensorTypeId, $data, $jsonData);

        } catch (\Exception $e) {
            $this->error("❌ Failed to process message: " . $e->getMessage());
            Log::error('RuuviTag JSON parse error', [
                'topic' => $topic,
                'message' => substr($message, 0, 200), // First 200 chars
                'error' => $e->getMessage()
            ]);
        }
    }

    private function processSensorType(string $sourceAddress, ?int $sensorTypeId, array $data, array $fullData): void
    {
        if (!$sensorTypeId || empty($data)) {
            return;
        }

        switch ($sensorTypeId) {
            case 112: // Temperature
                if (isset($data['temperature'])) {
                    $this->info("🌡️  Temperature: {$data['temperature']}°C");
                    $this->storeSensorData($sourceAddress, 'temperature', $data['temperature']);
                }
                break;

            case 114: // Humidity
                if (isset($data['humidity'])) {
                    $this->info("💧 Humidity: {$data['humidity']}%");
                    $this->storeSensorData($sourceAddress, 'humidity', $data['humidity']);
                }
                break;

            case 116: // Atmospheric Pressure
                if (isset($data['atmospheric_pressure'])) {
                    $this->info("🌪️  Pressure: {$data['atmospheric_pressure']} Pa");
                    $this->storeSensorData($sourceAddress, 'pressure', $data['atmospheric_pressure']);
                }
                break;

            case 127: // Movement/Accelerometer
                if (isset($data['state']) || isset($data['move_duration'])) {
                    $state = $data['state'] ?? 'unknown';
                    $this->info("🚶 Movement: {$state}");
                    $this->storeSensorData($sourceAddress, 'movement', $state);
                }
                break;

            case 142: // Battery/Voltage
                if (isset($data['voltage'])) {
                    $voltage = $data['voltage'];
                    $battery = $this->calculateBatteryPercent($voltage);
                    $this->info("🔋 Battery: {$battery}% (Voltage: {$voltage}V)");
                    $this->storeSensorData($sourceAddress, 'battery', $battery);
                }
                break;

            case 193: // Neighbors/Network
                if (isset($data['neighbors'])) {
                    $this->info("📶 Network: {$data['neighbors']} neighbors");
                }
                break;

            case 192: // Network Address
                if (isset($data['address'])) {
                    $this->info("📍 Address: {$data['address']}");
                }
                break;

            case 1: // Debug/Boot
                if (isset($data['debug_message'])) {
                    $this->info("🔧 Debug: {$data['debug_message']}");
                }
                break;

            default:
                $this->line("❓ Unknown sensor type {$sensorTypeId}: " . json_encode($data));
        }
    }

    private function storeSensorData(string $sourceAddress, string $dataType, $value): void
    {
        // Get or create the single RuuviTag sensor
        $sensor = $this->getOrCreateRuuviTag($sourceAddress);
        
        if (!$sensor) {
            $this->warn("⚠️  Could not get/create sensor for RuuviTag: {$sourceAddress}");
            return;
        }

        // Prepare data for storage
        $sensorDataFields = [
            'sensor_id' => $sensor->id,
            'timestamp' => now(),
        ];

        // Map data type to database columns
        switch ($dataType) {
            case 'temperature':
                $sensorDataFields['temperature'] = (float) $value;
                break;
            case 'humidity':
                $sensorDataFields['humidity'] = (float) $value;
                break;
            case 'pressure':
                $sensorDataFields['pressure'] = (float) $value;
                break;
            case 'movement':
                $sensorDataFields['door_state'] = $value === 'start-moving' ? 'moving' : 'still';
                break;
            case 'battery':
                // Update sensor battery level
                $sensor->update(['battery_level' => (int) $value]);
                return; // Don't store battery in sensor_data
        }

        // Store the data
        SensorData::create($sensorDataFields);

        // Update sensor last seen
        $sensor->update(['last_seen_at' => now()]);

        $this->info("✅ Stored {$dataType} data for RuuviTag: {$sensor->name}");
    }

    private function getOrCreateRuuviTag(string $sourceAddress): ?Sensor
    {
        // Check cache first
        if (isset($this->ruuvitagSensors[$sourceAddress])) {
            return $this->ruuvitagSensors[$sourceAddress];
        }

        // Try to find existing sensor by source_address
        $sensor = Sensor::where('source_address', $sourceAddress)->first();
        
        if (!$sensor) {
            // Auto-create ONE sensor per RuuviTag
            $org = Organization::first();
            if (!$org) {
                $this->error("❌ No organization found");
                return null;
            }

            // Create default building/room if needed
            $building = Building::firstOrCreate([
                'organization_id' => $org->id,
                'name' => 'RuuviTag Building'
            ], [
                'address' => 'Auto-detected from RuuviTag data'
            ]);

            $room = Room::firstOrCreate([
                'building_id' => $building->id,
                'name' => 'RuuviTag Room'
            ], [
                'floor' => 0,
                'surface_m2' => 25
            ]);

            // Create ONE sensor per RuuviTag (not per sensor type)
            $sensor = Sensor::create([
                'room_id' => $room->id,
                'source_address' => $sourceAddress,
                'name' => "RuuviTag {$sourceAddress}",
                'type' => 'ruuvitag',
                'position' => 'wall',
                'is_active' => true,
                'battery_level' => 100
            ]);

            $this->info("✅ Created single sensor for RuuviTag: {$sensor->name}");
        }

        // Cache it
        $this->ruuvitagSensors[$sourceAddress] = $sensor;
        
        return $sensor;
    }

    private function calculateBatteryPercent(float $voltage): int
    {
        // RuuviTag battery voltage range: ~2.0V (empty) to ~3.6V (full)
        $minVoltage = 2.0;
        $maxVoltage = 3.6;
        
        $percent = (($voltage - $minVoltage) / ($maxVoltage - $minVoltage)) * 100;
        
        return (int) max(0, min(100, $percent));
    }
}