<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MQTT Broker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the MQTT broker connection settings for the
    | EcoComfort IoT system. These settings are used to connect to the
    | MQTT broker and subscribe to sensor data topics.
    |
    */

    'host' => env('MQTT_HOST', 'localhost'),
    'port' => env('MQTT_PORT', 1883),
    'client_id' => env('MQTT_CLIENT_ID', 'ecocomfort_laravel'),
    'use_tls' => env('MQTT_USE_TLS', false),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | MQTT Topics
    |--------------------------------------------------------------------------
    |
    | Define the MQTT topics for different sensor data types. The EcoComfort
    | system listens to these topics to receive real-time sensor data.
    |
    */

    'topics' => [
        'temperature' => env('MQTT_TOPIC_TEMPERATURE', '112'),
        'humidity' => env('MQTT_TOPIC_HUMIDITY', '114'),
        'accelerometer' => env('MQTT_TOPIC_ACCELEROMETER', '127'),
    ],

    // Legacy support for individual topic configs
    'topic_temperature' => env('MQTT_TOPIC_TEMPERATURE', '112'),
    'topic_humidity' => env('MQTT_TOPIC_HUMIDITY', '114'),
    'topic_accelerometer' => env('MQTT_TOPIC_ACCELEROMETER', '127'),

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure the MQTT connection parameters for reliability and performance.
    |
    */

    'connection' => [
        'keep_alive_interval' => env('MQTT_KEEP_ALIVE', 60),
        'connect_timeout' => env('MQTT_CONNECT_TIMEOUT', 5),
        'socket_timeout' => env('MQTT_SOCKET_TIMEOUT', 5),
        'resend_timeout' => env('MQTT_RESEND_TIMEOUT', 10),
        'clean_session' => env('MQTT_CLEAN_SESSION', true),
        'will_topic' => env('MQTT_WILL_TOPIC'),
        'will_message' => env('MQTT_WILL_MESSAGE'),
        'will_qos' => env('MQTT_WILL_QOS', 0),
        'will_retain' => env('MQTT_WILL_RETAIN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality of Service (QoS) Levels
    |--------------------------------------------------------------------------
    |
    | Configure QoS levels for different types of sensor data.
    | 0 = At most once, 1 = At least once, 2 = Exactly once
    |
    */

    'qos' => [
        'temperature' => env('MQTT_QOS_TEMPERATURE', 0),
        'humidity' => env('MQTT_QOS_HUMIDITY', 0),
        'accelerometer' => env('MQTT_QOS_ACCELEROMETER', 1), // Higher QoS for critical door detection
        'alerts' => env('MQTT_QOS_ALERTS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Processing
    |--------------------------------------------------------------------------
    |
    | Configure how MQTT messages are processed and validated.
    |
    */

    'processing' => [
        'max_message_size' => env('MQTT_MAX_MESSAGE_SIZE', 1024),
        'message_timeout' => env('MQTT_MESSAGE_TIMEOUT', 30),
        'retry_attempts' => env('MQTT_RETRY_ATTEMPTS', 3),
        'batch_size' => env('MQTT_BATCH_SIZE', 100),
        'enable_message_validation' => env('MQTT_VALIDATE_MESSAGES', true),
        'log_raw_messages' => env('MQTT_LOG_RAW_MESSAGES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensor Data Validation
    |--------------------------------------------------------------------------
    |
    | Configure validation rules for incoming sensor data to ensure
    | data quality and prevent invalid readings from being processed.
    |
    */

    'validation' => [
        'temperature' => [
            'min' => env('MQTT_TEMP_MIN', -40),
            'max' => env('MQTT_TEMP_MAX', 85),
            'precision' => env('MQTT_TEMP_PRECISION', 2),
        ],
        'humidity' => [
            'min' => env('MQTT_HUMIDITY_MIN', 0),
            'max' => env('MQTT_HUMIDITY_MAX', 100),
            'precision' => env('MQTT_HUMIDITY_PRECISION', 2),
        ],
        'acceleration' => [
            'min' => env('MQTT_ACCEL_MIN', -16),
            'max' => env('MQTT_ACCEL_MAX', 16),
            'precision' => env('MQTT_ACCEL_PRECISION', 4),
        ],
        'battery' => [
            'min' => env('MQTT_BATTERY_MIN', 0),
            'max' => env('MQTT_BATTERY_MAX', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings for high-throughput environments.
    |
    */

    'performance' => [
        'enable_async_processing' => env('MQTT_ASYNC_PROCESSING', true),
        'queue_processing' => env('MQTT_QUEUE_PROCESSING', true),
        'cache_sensor_lookups' => env('MQTT_CACHE_SENSORS', true),
        'cache_duration' => env('MQTT_CACHE_DURATION', 300), // 5 minutes
        'max_concurrent_messages' => env('MQTT_MAX_CONCURRENT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging and Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure debugging and monitoring options for development and production.
    |
    */

    'debug' => [
        'log_level' => env('MQTT_LOG_LEVEL', 'info'),
        'log_channel' => env('MQTT_LOG_CHANNEL', 'mqtt'),
        'enable_metrics' => env('MQTT_ENABLE_METRICS', true),
        'metrics_interval' => env('MQTT_METRICS_INTERVAL', 60),
        'health_check_interval' => env('MQTT_HEALTH_CHECK', 300),
    ],

];