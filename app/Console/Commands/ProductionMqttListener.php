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

class ProductionMqttListener extends Command
{
    protected $signature = 'mqtt:production {--timeout=0 : Timeout in seconds (0 = no timeout)}';
    protected $description = 'PRODUCTION MQTT Listener - Real RuuviTag data from Pi (gw-event/status/+)';

    private MqttClient $mqtt;
    private array $knownSensors = [];

    public function handle(): int
    {
        $this->info('🚀 PRODUCTION MQTT LISTENER - Real RuuviTag Data');
        $this->info('📡 Pi MQTT: 192.168.1.216:1883');
        $this->info('📊 Topic: gw-event/status/+');
        $this->line('=====================================');

        try {
            // Initialize Pi MQTT connection
            $this->mqtt = new MqttClient('192.168.1.216', 1883, 'ecocomfort_production');
            
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
        // Extract sensor ID from topic: gw-event/status/202481587021839
        if (!preg_match('/gw-event\/status\/(\d+)/', $topic, $matches)) {
            return;
        }

        $sensorId = $matches[1];
        $this->line("📡 Sensor ID: {$sensorId}");

        try {
            // Decode RuuviTag binary data
            $data = $this->decodeRuuviTagData($message);
            
            if ($data) {
                $this->info("🌡️  Temperature: {$data['temperature']}°C");
                $this->info("💧 Humidity: {$data['humidity']}%");
                $this->info("🔋 Battery: {$data['battery']}%");
                
                // Store in database
                $this->storeRealSensorData($sensorId, $data);
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to process sensor {$sensorId}: " . $e->getMessage());
            Log::error('RuuviTag decode error', [
                'sensor_id' => $sensorId,
                'topic' => $topic,
                'message_length' => strlen($message),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function decodeRuuviTagData(string $binaryData): ?array
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
                'battery' => round(min(100, max(0, ($battery - 1.6) / (3.6 - 1.6) * 100)), 0),
                'timestamp' => now()
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    private function storeRealSensorData(string $ruuviTagId, array $data): void
    {
        // Get or create sensor based on real RuuviTag ID
        $sensor = $this->getOrCreateSensor($ruuviTagId);
        
        if (!$sensor) {
            $this->warn("⚠️  No sensor found/created for RuuviTag: {$ruuviTagId}");
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

        $this->info("✅ Stored real data for sensor: {$sensor->name}");
    }

    private function getOrCreateSensor(string $ruuviTagId): ?Sensor
    {
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
                $this->error("❌ No organization found - cannot create sensor");
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

            $this->info("✅ Auto-created sensor: {$sensor->name}");
        }

        // Cache it
        $this->knownSensors[$ruuviTagId] = $sensor;
        
        return $sensor;
    }
}
