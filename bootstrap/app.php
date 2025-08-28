<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        
        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\Cors::class,
        ]);
        
        // Middleware aliases
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtMiddleware::class,
            'api.throttle' => \App\Http\Middleware\ApiRateLimiter::class,
            'cors' => \App\Http\Middleware\Cors::class,
            'security' => \App\Http\Middleware\SecurityHeaders::class,
        ]);
        
        // Throttle middleware configurations
        // $middleware->throttleApi(limiter: 'api'); // Disabled for development
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
