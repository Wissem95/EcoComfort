<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Check if token exists and is valid
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                Log::warning('JWT: User not found', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                ]);
                
                return response()->json([
                    'error' => 'User not found',
                    'code' => 'USER_NOT_FOUND'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Add user to request for controllers
            $request->merge(['auth_user' => $user]);
            
            // Log successful authentication for monitoring
            Log::info('JWT: Authentication successful', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
            ]);
            
        } catch (TokenExpiredException $e) {
            Log::warning('JWT: Token expired', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'token_expired_at' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Token expired',
                'code' => 'TOKEN_EXPIRED'
            ], Response::HTTP_UNAUTHORIZED);
            
        } catch (TokenInvalidException $e) {
            Log::warning('JWT: Token invalid', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Token invalid',
                'code' => 'TOKEN_INVALID'
            ], Response::HTTP_UNAUTHORIZED);
            
        } catch (JWTException $e) {
            Log::error('JWT: Token absent or malformed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Authorization token required',
                'code' => 'TOKEN_REQUIRED'
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        return $next($request);
    }
}