<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ApiRateLimiter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $maxAttempts
     * @param  int  $decayMinutes
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '100', int $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = (int) $maxAttempts;
        
        // Check if the user has exceeded the rate limit
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            // Log rate limit exceeded
            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'max_attempts' => $maxAttempts,
                'retry_after' => $retryAfter,
                'user_id' => $request->user()?->id,
            ]);
            
            // Increment suspicious activity counter for security monitoring
            $this->trackSuspiciousActivity($request);
            
            return response()->json([
                'error' => 'Too Many Requests',
                'message' => "Rate limit exceeded. Try again in {$retryAfter} seconds.",
                'retry_after' => $retryAfter,
                'max_attempts' => $maxAttempts,
                'window' => $decayMinutes
            ], Response::HTTP_TOO_MANY_REQUESTS)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
                'Retry-After' => $retryAfter,
            ]);
        }
        
        // Hit the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        // Add rate limit headers to response
        $remaining = RateLimiter::retriesLeft($key, $maxAttempts);
        $resetTime = now()->addMinutes($decayMinutes)->timestamp;
        
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => $resetTime,
        ]);
    }
    
    /**
     * Resolve the request signature for rate limiting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        
        if ($user) {
            // Rate limit by user ID for authenticated requests
            return "api:user:{$user->id}:{$request->path()}";
        }
        
        // Rate limit by IP for unauthenticated requests
        return "api:ip:{$request->ip()}:{$request->path()}";
    }
    
    /**
     * Track suspicious activity for security monitoring
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function trackSuspiciousActivity(Request $request): void
    {
        $key = "suspicious_activity:" . $request->ip();
        $attempts = Cache::increment($key, 1);
        
        // Set expiry for the counter (1 hour)
        if ($attempts === 1) {
            Cache::put($key, 1, now()->addHour());
        }
        
        // Log high suspicious activity
        if ($attempts >= 10) {
            Log::alert('High suspicious activity detected', [
                'ip' => $request->ip(),
                'attempts_in_hour' => $attempts,
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->path(),
                'timestamp' => now()->toISOString(),
            ]);
        }
    }
}