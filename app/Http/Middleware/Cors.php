<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS requests
        if ($request->getMethod() === "OPTIONS") {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $this->getAllowedOrigin($request))
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization,X-CSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        // Add CORS headers to all responses
        return $response
            ->header('Access-Control-Allow-Origin', $this->getAllowedOrigin($request))
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Authorization,X-CSRF-TOKEN')
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Expose-Headers', 'Content-Length,Content-Range,X-RateLimit-Limit,X-RateLimit-Remaining,X-RateLimit-Reset');
    }

    /**
     * Get the allowed origin based on the request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function getAllowedOrigin(Request $request): string
    {
        $origin = $request->headers->get('Origin');
        
        // Define allowed origins
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:5173',
            'http://localhost:8080',
            'https://localhost:3000',
            'https://localhost:5173',
            'https://localhost:8080',
        ];

        // Add domain from environment if configured
        if ($domain = config('app.domain')) {
            $allowedOrigins[] = "https://{$domain}";
            $allowedOrigins[] = "http://{$domain}";
        }

        // Check if the origin is in our allowed list
        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }

        // Default to the first allowed origin for development
        if (config('app.env') === 'local' || config('app.env') === 'development') {
            return $origin ?: $allowedOrigins[0];
        }

        // For production, be more restrictive
        return config('app.url');
    }
}