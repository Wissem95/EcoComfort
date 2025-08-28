<?php

namespace App\Console\Commands;

use App\Services\MQTTSubscriberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MqttListenerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen {--timeout=0 : Timeout in seconds (0 = no timeout)} {--bridge : Enable bridge mode (Pi MQTT → HiveMQ Cloud)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to MQTT messages and process sensor data, or bridge from Pi to HiveMQ Cloud';

    /**
     * Execute the console command.
     */
    public function handle(MQTTSubscriberService $mqttService): int
    {
        $timeout = (int) $this->option('timeout');
        $bridgeMode = $this->option('bridge');
        // Handle bridge mode
        if ($bridgeMode) {
            return $this->handleBridgeMode($mqttService, $timeout);
        }
        
        // Handle normal mode (existing functionality)
        return $this->handleNormalMode($mqttService, $timeout);
    }
    
    private function handleBridgeMode(MQTTSubscriberService $mqttService, int $timeout): int
    {
        $this->info('🌉 Starting MQTT Bridge Mode...');
        $this->info('📥 Source: Pi MQTT (192.168.1.216:1883)');
        $this->info('📤 Destination: HiveMQ Cloud (' . config('mqtt.host') . ':' . config('mqtt.port') . ')');
        $this->newLine();
        
        // Setup signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            $this->info('✅ Signal handlers registered for graceful shutdown');
        }
        
        try {
            // Start bridge mode
            $mqttService->bridgeMode();
            
        } catch (\Exception $e) {
            $this->error('❌ Bridge mode failed: ' . $e->getMessage());
            
            if ($this->getOutput()->isVerbose()) {
                $this->error('📋 Error details: ' . $e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
        
        return self::SUCCESS;
    }
    
    private function handleNormalMode(MQTTSubscriberService $mqttService, int $timeout): int
    {
        $this->info('🚀 Starting MQTT listener...');
        $this->info('📡 Connecting to MQTT broker: ' . config('mqtt.host') . ':' . config('mqtt.port'));
        
        try {
            // Connect to MQTT broker
            $mqttService->connect();
            $this->info('✅ Connected to MQTT broker successfully');
            
            // Subscribe to topics
            $mqttService->subscribe();
            $this->info('🔔 Subscribed to topics:');
            $this->line('  - Temperature: ' . config('mqtt.topic_temperature'));
            $this->line('  - Humidity: ' . config('mqtt.topic_humidity'));
            $this->line('  - Accelerometer: ' . config('mqtt.topic_accelerometer'));
            
            $this->info('👂 Listening for messages...');
            
            if ($timeout > 0) {
                $this->info("⏰ Will stop after {$timeout} seconds");
                $startTime = time();
            }
            
            // Listen for messages
            while (true) {
                try {
                    // Process messages for 1 second
                    $mqttService->listen();
                    
                    // Check timeout
                    if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                        $this->info('⏰ Timeout reached, stopping...');
                        break;
                    }
                    
                    // Brief pause to prevent excessive CPU usage
                    usleep(100000); // 0.1 seconds
                    
                } catch (\Exception $e) {
                    $this->error('❌ Error processing messages: ' . $e->getMessage());
                    Log::error('MQTT listener error: ' . $e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    // Brief pause before retrying
                    sleep(1);
                }
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to start MQTT listener: ' . $e->getMessage());
            Log::error('MQTT listener startup error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            return Command::FAILURE;
        } finally {
            // Ensure proper cleanup
            try {
                $mqttService->disconnect();
                $this->info('🔌 Disconnected from MQTT broker');
            } catch (\Exception $e) {
                Log::warning('Error during MQTT disconnect: ' . $e->getMessage());
            }
        }
        
        $this->info('🛑 MQTT listener stopped');
        return Command::SUCCESS;
    }
    
    /**
     * Handle graceful shutdown signal
     */
    public function handleShutdown(int $signal): void
    {
        $this->warn("\n📡 Received signal {$signal}, shutting down gracefully...");
        
        // The bridge will handle its own cleanup via try/catch in bridgeLoop
        // Normal mode will handle cleanup via finally block
        
        Log::info('MQTT listener received shutdown signal', ['signal' => $signal]);
        
        // Exit gracefully
        exit(0);
    }
}