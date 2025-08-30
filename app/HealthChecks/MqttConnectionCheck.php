<?php

namespace App\HealthChecks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttConnectionCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();
        
        try {
            $host = config('mqtt.host', 'localhost');
            $port = config('mqtt.port', 1883);
            $username = config('mqtt.username', '');
            $password = config('mqtt.password', '');
            
            $client = new MqttClient($host, $port, 'health_check_client');
            
            $connectionSettings = new ConnectionSettings();
            if ($username && $password) {
                $connectionSettings = $connectionSettings->setCredentials($username, $password);
            }
            $connectionSettings = $connectionSettings->setConnectTimeout(5);
            
            $startTime = microtime(true);
            $client->connect($connectionSettings);
            $connectionTime = (microtime(true) - $startTime) * 1000;
            
            if ($client->isConnected()) {
                $client->disconnect();
                
                return $result->ok("MQTT broker connection successful")
                    ->shortSummary("Connected to {$host}:{$port}")
                    ->meta([
                        'host' => $host,
                        'port' => $port,
                        'connection_time_ms' => round($connectionTime, 2),
                    ]);
            } else {
                return $result->failed("Failed to establish MQTT connection")
                    ->shortSummary("Cannot connect to {$host}:{$port}")
                    ->meta(['host' => $host, 'port' => $port]);
            }
            
        } catch (\Exception $e) {
            return $result->failed("MQTT connection error: {$e->getMessage()}")
                ->shortSummary("MQTT broker unavailable")
                ->meta([
                    'error' => $e->getMessage(),
                    'host' => $host ?? 'unknown',
                    'port' => $port ?? 'unknown',
                ]);
        }
    }
}