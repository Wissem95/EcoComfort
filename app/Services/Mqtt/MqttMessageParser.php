<?php

namespace App\Services\Mqtt;

use App\Data\MqttMessageData;
use Illuminate\Support\Facades\Log;

class MqttMessageParser
{
    public function parse(string $topic, string $message): ?MqttMessageData
    {
        try {
            $payload = json_decode($message, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("Failed to decode MQTT JSON message", [
                    'topic' => $topic,
                    'error' => json_last_error_msg(),
                    'message_preview' => substr($message, 0, 100)
                ]);
                return null;
            }

            return $this->createMqttMessageData($topic, $payload);
            
        } catch (\Exception $e) {
            Log::error("Error parsing MQTT message", [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'message_preview' => substr($message, 0, 100)
            ]);
            return null;
        }
    }

    private function createMqttMessageData(string $topic, array $payload): MqttMessageData
    {
        // Handle RuuviTag format (pws-packet/sourceAddress/sensorType)
        if (str_starts_with($topic, 'pws-packet/')) {
            return MqttMessageData::fromRuuviTag($topic, $payload);
        }
        
        // Handle legacy format (gw-event/status/sensorId)
        if (str_starts_with($topic, 'gw-event/')) {
            return $this->parseLegacyFormat($topic, $payload);
        }

        // Default format
        return new MqttMessageData(
            topic: $topic,
            payload: $payload,
            sourceAddress: $payload['sourceAddress'] ?? 0,
            sensorTypeId: $payload['sensorType'] ?? 0,
        );
    }

    private function parseLegacyFormat(string $topic, array $payload): MqttMessageData
    {
        // Extract sensor ID from topic: gw-event/status/123
        preg_match('/gw-event\/status\/(\d+)/', $topic, $matches);
        $sourceAddress = isset($matches[1]) ? (int) $matches[1] : 0;

        // Determine sensor type from data content
        $sensorTypeId = $this->determineSensorType($payload['data'] ?? []);

        return new MqttMessageData(
            topic: $topic,
            payload: $payload,
            sourceAddress: $sourceAddress,
            sensorTypeId: $sensorTypeId,
            txTimeMs: $payload['queueDelay'] ?? null,
            eventId: $payload['eventId'] ?? null,
        );
    }

    private function determineSensorType(array $data): int
    {
        // Map data fields to sensor type IDs
        if (isset($data['temperature'])) return 112;
        if (isset($data['humidity'])) return 114;
        if (isset($data['pressure'])) return 116;
        if (isset($data['accelerometer'])) return 127;
        if (isset($data['batteryVoltage'])) return 142;
        
        return 0; // Unknown type
    }

    public function validateMessage(MqttMessageData $message): array
    {
        $errors = [];
        
        if (empty($message->topic)) {
            $errors[] = 'Topic is required';
        }
        
        if (empty($message->payload)) {
            $errors[] = 'Payload is required';
        }
        
        if ($message->sourceAddress <= 0) {
            $errors[] = 'Valid source address is required';
        }

        // Validate data format based on sensor type
        $validationErrors = $this->validateDataFormat($message);
        $errors = array_merge($errors, $validationErrors);
        
        return $errors;
    }

    private function validateDataFormat(MqttMessageData $message): array
    {
        $errors = [];
        
        switch ($message->getDataType()) {
            case 'temperature':
                $temp = $message->extractTemperature();
                if ($temp === null) {
                    $errors[] = 'Temperature data is missing or invalid';
                } elseif ($temp < -50 || $temp > 100) {
                    $errors[] = 'Temperature value out of reasonable range (-50°C to 100°C)';
                }
                break;
                
            case 'humidity':
                $humidity = $message->extractHumidity();
                if ($humidity === null) {
                    $errors[] = 'Humidity data is missing or invalid';
                } elseif ($humidity < 0 || $humidity > 100) {
                    $errors[] = 'Humidity value out of range (0% to 100%)';
                }
                break;
                
            case 'movement':
                $movement = $message->extractMovementData();
                if ($movement === null || empty($movement)) {
                    $errors[] = 'Movement data is missing or invalid';
                } else {
                    // Validate accelerometer values are within reasonable range
                    foreach (['x_axis', 'y_axis', 'z_axis'] as $axis) {
                        if (isset($movement[$axis]) && (abs($movement[$axis]) > 2000)) {
                            $errors[] = "Accelerometer {$axis} value out of range";
                        }
                    }
                }
                break;
        }
        
        return $errors;
    }

    public function isValidRuuviTagMessage(string $topic, array $payload): bool
    {
        // Check topic format
        if (!str_starts_with($topic, 'pws-packet/')) {
            return false;
        }
        
        // Check required fields
        $requiredFields = ['tx_time_ms_epoch'];
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                return false;
            }
        }
        
        return true;
    }
}