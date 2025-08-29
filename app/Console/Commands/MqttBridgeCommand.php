<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttBridgeCommand extends Command
{
    protected $signature = 'mqtt:bridge 
                            {--timeout=0 : Timeout in seconds (0 = infinite)}';
    
    protected $description = 'Bridge MQTT messages from Raspberry Pi to HiveMQ Cloud';
    
    private ?MqttClient $sourceMqtt = null;
    private ?MqttClient $destMqtt = null;
    private int $messageCount = 0;
    private int $errorCount = 0;
    private bool $running = true;
    
    // RuuviTag sensor IDs to bridge - focus on specific gateway
    private array $sensorTopics = [
        'pws-packet/202481601481463/+/+', // Specific gateway with all sensors
        '#', // Fallback for any other potential topics
    ];
    
    public function handle(): int
    {
        $this->info('🌉 Starting MQTT Bridge: Raspberry Pi → HiveMQ Cloud');
        $this->newLine();
        
        // Setup signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }
        
        try {
            // Connect to source (Raspberry Pi)
            $this->connectToSource();
            
            // Connect to destination (HiveMQ Cloud)
            $this->connectToDestination();
            
            // Subscribe to all sensor topics
            $this->subscribeToTopics();
            
            // Start bridging
            $this->startBridging();
            
        } catch (\Exception $e) {
            $this->error('❌ Bridge failed: ' . $e->getMessage());
            Log::error('MQTT Bridge error', ['exception' => $e]);
            return self::FAILURE;
        } finally {
            $this->cleanup();
        }
        
        $this->info('✅ Bridge stopped successfully');
        $this->info("📊 Total messages bridged: {$this->messageCount}");
        if ($this->errorCount > 0) {
            $this->warn("⚠️  Errors encountered: {$this->errorCount}");
        }
        
        return self::SUCCESS;
    }
    
    private function connectToSource(): void
    {
        $host = env('MQTT_PI_HOST', '192.168.68.114');
        $port = (int) env('MQTT_PI_PORT', 1883);
        
        $this->info("📥 Connecting to Raspberry Pi MQTT broker...");
        $this->info("   Host: {$host}:{$port}");
        
        $this->sourceMqtt = new MqttClient($host, $port, 'ecocomfort_bridge_source');
        
        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(60)
            ->setConnectTimeout(5)
            ->setSocketTimeout(5);
        
        $this->sourceMqtt->connect($connectionSettings);
        $this->info('✅ Connected to Raspberry Pi broker');
    }
    
    private function connectToDestination(): void
    {
        $host = env('MQTT_CLOUD_HOST', env('MQTT_HOST'));
        $port = (int) env('MQTT_CLOUD_PORT', env('MQTT_PORT', 8883));
        $username = env('MQTT_CLOUD_USERNAME', env('MQTT_USERNAME'));
        $password = env('MQTT_CLOUD_PASSWORD', env('MQTT_PASSWORD'));
        
        $this->info("☁️  Connecting to HiveMQ Cloud...");
        $this->info("   Host: {$host}:{$port}");
        
        $this->destMqtt = new MqttClient($host, $port, 'ecocomfort_bridge_dest');
        
        $connectionSettings = (new ConnectionSettings)
            ->setKeepAliveInterval(60)
            ->setConnectTimeout(10)
            ->setSocketTimeout(10)
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(true)
            ->setTlsVerifyPeer(false)
            ->setTlsVerifyPeerName(false)
            ->setUsername($username)
            ->setPassword($password);
        
        $this->destMqtt->connect($connectionSettings);
        $this->info('✅ Connected to HiveMQ Cloud');
    }
    
    private function subscribeToTopics(): void
    {
        $this->info('📡 Subscribing to sensor topics:');
        
        foreach ($this->sensorTopics as $topic) {
            $this->sourceMqtt->subscribe(
                $topic,
                function (string $topic, string $message) {
                    $this->bridgeMessage($topic, $message);
                },
                0 // QoS level
            );
            
            $this->line("   ✓ Topic {$topic} - All MQTT topics (will filter for RuuviTag data)");
        }
        
        $this->info('✅ All topics subscribed');
    }
    
    private function bridgeMessage(string $topic, string $message): void
    {
        try {
            // Parse message to check if it's RuuviTag data
            $data = json_decode($message, true);
            
            // Only bridge messages that contain sensor_id (RuuviTag data)
            if (!$data || !isset($data['sensor_id'])) {
                // Skip binary/non-JSON messages and non-sensor messages
                if ($this->output->isVerbose()) {
                    $this->line("  ⏭️  Skipping non-sensor message on [{$topic}]");
                }
                return;
            }
            
            $sensorId = $data['sensor_id'];
            $sensorData = isset($data['data']) ? $data['data'] : [];
            
            // Show details if verbose
            if ($this->output->isVerbose()) {
                $this->info("→ Bridging [{$topic}]: sensor_id={$sensorId}, data=" . json_encode($sensorData));
            }
            
            // Create a clean topic name based on sensor_id for HiveMQ
            $cleanTopic = (string)$sensorId;
            
            // Publish to HiveMQ Cloud with sensor_id as topic
            $this->destMqtt->publish($cleanTopic, $message, 0, false);
            
            $this->messageCount++;
            
            // Show progress every 5 messages if not verbose
            if (!$this->output->isVerbose() && $this->messageCount % 5 == 0) {
                $this->info("📊 RuuviTag messages bridged: {$this->messageCount}");
            }
            
        } catch (\Exception $e) {
            $this->errorCount++;
            $this->error("❌ Failed to bridge message on topic {$topic}: " . $e->getMessage());
            Log::error('Bridge message failed', [
                'topic' => $topic,
                'message' => $message,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function startBridging(): void
    {
        $timeout = (int) $this->option('timeout');
        $startTime = time();
        
        $this->info('🚀 Bridge is running...');
        $this->info('   Press Ctrl+C to stop');
        
        if ($timeout > 0) {
            $this->info("⏱️  Will stop after {$timeout} seconds");
        }
        
        $this->newLine();
        
        while ($this->running) {
            try {
                // Process messages (blocking call for 1 second)
                $this->sourceMqtt->loop(true, true);
                
                // Check timeout
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    $this->info('⏰ Timeout reached, stopping bridge...');
                    break;
                }
                
                // Check signal handlers
                if (extension_loaded('pcntl')) {
                    pcntl_signal_dispatch();
                }
                
            } catch (\Exception $e) {
                $this->error('❌ Bridge loop error: ' . $e->getMessage());
                Log::error('Bridge loop error', ['exception' => $e]);
                sleep(1); // Brief pause before retry
            }
        }
    }
    
    private function getTopicDescription(string $topic): string
    {
        return match($topic) {
            '112' => 'Temperature',
            '114' => 'Humidity',
            '116' => 'Atmospheric Pressure',
            '127' => 'Accelerometer/Movement',
            '142' => 'Battery Voltage',
            '192' => 'BLE Neighbors Details',
            '193' => 'BLE Neighbors Count',
            default => 'Unknown'
        };
    }
    
    private function cleanup(): void
    {
        $this->info('🔌 Cleaning up connections...');
        
        if ($this->sourceMqtt) {
            try {
                $this->sourceMqtt->disconnect();
                $this->info('   ✓ Disconnected from Raspberry Pi');
            } catch (\Exception $e) {
                Log::warning('Failed to disconnect from source', ['error' => $e->getMessage()]);
            }
        }
        
        if ($this->destMqtt) {
            try {
                $this->destMqtt->disconnect();
                $this->info('   ✓ Disconnected from HiveMQ Cloud');
            } catch (\Exception $e) {
                Log::warning('Failed to disconnect from destination', ['error' => $e->getMessage()]);
            }
        }
    }
    
    public function handleShutdown(int $signal): void
    {
        $this->newLine();
        $this->warn("🛑 Received signal {$signal}, shutting down gracefully...");
        $this->running = false;
    }
}