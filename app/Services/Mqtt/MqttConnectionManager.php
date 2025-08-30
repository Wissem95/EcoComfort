<?php

namespace App\Services\Mqtt;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttConnectionManager
{
    private ?MqttClient $client = null;
    private ?MqttClient $bridgeSourceClient = null;
    private ?MqttClient $bridgeDestinationClient = null;
    
    public function __construct(
        private string $host = 'localhost',
        private int $port = 1883,
        private string $username = '',
        private string $password = '',
        private string $clientId = 'ecocomfort_client'
    ) {}

    public function connect(): MqttClient
    {
        if ($this->client && $this->client->isConnected()) {
            return $this->client;
        }

        $this->client = new MqttClient(
            $this->host,
            $this->port,
            $this->clientId,
            MqttClient::MQTT_3_1_1
        );

        $connectionSettings = new ConnectionSettings();
        
        if ($this->username && $this->password) {
            $connectionSettings = $connectionSettings->setCredentials($this->username, $this->password);
        }
        
        $connectionSettings = $connectionSettings
            ->setKeepAliveInterval(60)
            ->setConnectTimeout(30)
            ->setUseTls(false)
            ->setTlsSelfSignedAllowed(false);

        try {
            $this->client->connect($connectionSettings);
            Log::info("MQTT client connected", [
                'host' => $this->host,
                'port' => $this->port,
                'client_id' => $this->clientId
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to connect to MQTT broker", [
                'error' => $e->getMessage(),
                'host' => $this->host,
                'port' => $this->port
            ]);
            throw $e;
        }

        return $this->client;
    }

    public function disconnect(): void
    {
        if ($this->client && $this->client->isConnected()) {
            $this->client->disconnect();
            Log::info("MQTT client disconnected");
        }
        
        if ($this->bridgeSourceClient && $this->bridgeSourceClient->isConnected()) {
            $this->bridgeSourceClient->disconnect();
        }
        
        if ($this->bridgeDestinationClient && $this->bridgeDestinationClient->isConnected()) {
            $this->bridgeDestinationClient->disconnect();
        }
    }

    public function subscribe(string $topic, callable $messageHandler, int $qos = 0): void
    {
        $client = $this->connect();
        
        $client->subscribe($topic, $messageHandler, $qos);
        Log::info("Subscribed to MQTT topic", ['topic' => $topic, 'qos' => $qos]);
    }

    public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void
    {
        $client = $this->connect();
        
        $client->publish($topic, $message, $qos, $retain);
        Log::debug("Published MQTT message", [
            'topic' => $topic, 
            'message_length' => strlen($message),
            'qos' => $qos,
            'retain' => $retain
        ]);
    }

    public function loop(int $timeout = 1): void
    {
        if ($this->client && $this->client->isConnected()) {
            $this->client->loop($timeout);
        }
    }

    public function isConnected(): bool
    {
        return $this->client && $this->client->isConnected();
    }

    public function setupBridge(array $sourceConfig, array $destinationConfig): void
    {
        // Source connection (Pi MQTT)
        $this->bridgeSourceClient = new MqttClient(
            $sourceConfig['host'],
            $sourceConfig['port'],
            $sourceConfig['client_id'] ?? 'bridge_source',
            MqttClient::MQTT_3_1_1
        );

        // Destination connection (HiveMQ Cloud)
        $this->bridgeDestinationClient = new MqttClient(
            $destinationConfig['host'],
            $destinationConfig['port'],
            $destinationConfig['client_id'] ?? 'bridge_destination',
            MqttClient::MQTT_3_1_1
        );

        // Connect source
        $sourceSettings = new ConnectionSettings();
        if (isset($sourceConfig['username'])) {
            $sourceSettings = $sourceSettings->setCredentials(
                $sourceConfig['username'], 
                $sourceConfig['password']
            );
        }
        $this->bridgeSourceClient->connect($sourceSettings);

        // Connect destination
        $destSettings = new ConnectionSettings();
        if (isset($destinationConfig['username'])) {
            $destSettings = $destSettings->setCredentials(
                $destinationConfig['username'], 
                $destinationConfig['password']
            );
        }
        $destSettings = $destSettings->setUseTls(true);
        $this->bridgeDestinationClient->connect($destSettings);

        Log::info("MQTT bridge connections established");
    }

    public function bridgeMessage(string $sourceTopic, string $destinationTopic, string $message): void
    {
        if ($this->bridgeDestinationClient && $this->bridgeDestinationClient->isConnected()) {
            $this->bridgeDestinationClient->publish($destinationTopic, $message);
            
            Log::debug("Bridged MQTT message", [
                'source_topic' => $sourceTopic,
                'destination_topic' => $destinationTopic,
                'message_length' => strlen($message)
            ]);
        }
    }

    public function subscribeToBridge(string $topic, callable $bridgeHandler): void
    {
        if ($this->bridgeSourceClient && $this->bridgeSourceClient->isConnected()) {
            $this->bridgeSourceClient->subscribe($topic, $bridgeHandler);
        }
    }

    public function loopBridge(int $timeout = 1): void
    {
        if ($this->bridgeSourceClient && $this->bridgeSourceClient->isConnected()) {
            $this->bridgeSourceClient->loop($timeout);
        }
    }
}