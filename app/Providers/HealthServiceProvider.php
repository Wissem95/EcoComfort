<?php

namespace App\Providers;

use App\HealthChecks\MqttConnectionCheck;
use App\HealthChecks\SensorDataFreshnessCheck;
use App\HealthChecks\PerformanceCheck;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Checks\Checks\RedisCheck;

class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Health::checks([
            // Database connectivity
            DatabaseCheck::new()
                ->name('database')
                ->connectionName(config('database.default')),
                
            // Cache functionality
            CacheCheck::new()
                ->name('cache'),
                
            // Redis connectivity (for cache and queues)
            RedisCheck::new()
                ->name('redis')
                ->connectionName('default'),
                
            // Disk space monitoring
            UsedDiskSpaceCheck::new()
                ->name('disk_space')
                ->warnWhenUsedSpaceIsAbovePercentage(80)
                ->failWhenUsedSpaceIsAbovePercentage(90),
                
            // Custom health checks according to spec
            MqttConnectionCheck::new()
                ->name('mqtt_connection')
                ->everyMinute(), // Check every minute
                
            SensorDataFreshnessCheck::new()
                ->name('sensor_data_freshness')
                ->everyFiveMinutes(), // Check every 5 minutes
                
            PerformanceCheck::new()
                ->name('system_performance')
                ->everyFiveMinutes(), // Check every 5 minutes
        ]);
    }

    public function register(): void
    {
        //
    }
}