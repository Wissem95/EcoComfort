<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
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
        $response = $next($request);

        // X-Frame-Options: Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options: Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection: Enable XSS filtering
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer-Policy: Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content-Security-Policy: Prevent XSS and other injection attacks
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "connect-src 'self' ws: wss:",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        // X-Permitted-Cross-Domain-Policies: Restrict cross-domain policies
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Feature-Policy/Permissions-Policy: Control browser features
        $permissions = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'accelerometer=(self)',
            'gyroscope=(self)',
        ];
        $response->headers->set('Permissions-Policy', implode(', ', $permissions));

        // Only add HTTPS security headers if using HTTPS
        if ($request->isSecure()) {
            // Strict-Transport-Security: Force HTTPS connections
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );

            // Expect-CT: Certificate Transparency monitoring
            $response->headers->set(
                'Expect-CT',
                'max-age=86400, enforce'
            );
        }

        // X-API-Version: Add API version for debugging
        $response->headers->set('X-API-Version', '1.0.0');

        // X-Response-Time: Add response time for monitoring
        if ($request->hasHeader('X-Request-Start')) {
            $startTime = (float) $request->header('X-Request-Start');
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $response->headers->set('X-Response-Time', $responseTime . 'ms');
        }

        return $response;
    }
}