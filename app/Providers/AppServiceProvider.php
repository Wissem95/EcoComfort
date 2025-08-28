<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind services to the container to resolve dependency injection
        $this->app->singleton(\App\Services\GamificationService::class, function ($app) {
            return new \App\Services\GamificationService();
        });

        $this->app->singleton(\App\Services\NotificationService::class, function ($app) {
            return new \App\Services\NotificationService(
                $app->make(\App\Services\GamificationService::class)
            );
        });

        $this->app->singleton(\App\Services\EnergyCalculatorService::class, function ($app) {
            return new \App\Services\EnergyCalculatorService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // API rate limiting - 100 requests per minute for authenticated users
        RateLimiter::for('api', function (Request $request) {
            if ($request->user()) {
                return Limit::perMinute(100)->by($request->user()->id);
            }
            
            // Stricter limits for unauthenticated requests
            return Limit::perMinute(30)->by($request->ip());
        });

        // Authentication rate limiting - 10 attempts per minute to prevent brute force
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // WebSocket connection rate limiting
        RateLimiter::for('websocket', function (Request $request) {
            if ($request->user()) {
                return Limit::perMinute(50)->by($request->user()->id);
            }
            
            return Limit::perMinute(10)->by($request->ip());
        });

        // Sensor data submission rate limiting - higher limits for IoT devices
        RateLimiter::for('sensor-data', function (Request $request) {
            if ($request->user()) {
                return [
                    Limit::perMinute(1000)->by($request->user()->id), // 1000 per minute
                    Limit::perHour(50000)->by($request->user()->id),  // 50000 per hour
                ];
            }
            
            return Limit::perMinute(100)->by($request->ip());
        });

        // Admin actions rate limiting
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(200)->by($request->user()?->id ?? $request->ip());
        });

        // Password reset rate limiting
        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip()),    // 3 per minute
                Limit::perHour(10)->by($request->ip()),     // 10 per hour
            ];
        });

        // File upload rate limiting
        RateLimiter::for('uploads', function (Request $request) {
            if ($request->user()) {
                return [
                    Limit::perMinute(20)->by($request->user()->id),  // 20 uploads per minute
                    Limit::perDay(500)->by($request->user()->id),    // 500 per day
                ];
            }
            
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
