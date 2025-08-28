<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\User;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Login user and create token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        // Validate login credentials
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Invalid email or password format',
                'details' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        $credentials = $request->only('email', 'password');

        try {
            // Attempt to create token with credentials
            if (!$token = JWTAuth::attempt($credentials)) {
                Log::warning('Authentication failed', [
                    'email' => $request->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'error' => 'Invalid credentials',
                    'message' => 'Email or password is incorrect'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Get the authenticated user
            $user = Auth::user();
            
            // Update last login timestamp and IP
            $user->update([
                'last_login_at' => Carbon::now(),
                'last_login_ip' => $request->ip(),
            ]);

            // Log successful authentication
            Log::info('User authenticated successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->respondWithToken($token, $user);
            
        } catch (JWTException $e) {
            Log::error('JWT Authentication error', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'error' => 'Could not create token',
                'message' => 'Authentication service temporarily unavailable'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Register new user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        // Validate registration data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Invalid registration data',
                'details' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Create new user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'organization_id' => $request->organization_id,
                'email_verified_at' => Carbon::now(), // Auto-verify for now
            ]);

            // Create token for the new user
            $token = JWTAuth::fromUser($user);

            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return $this->respondWithToken($token, $user, Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'error' => 'Registration failed',
                'message' => 'Could not create user account'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the authenticated User
     *
     * @return \Illuminate\Http\Response
     */
    public function user(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Load relationships
            $user->load(['organization', 'gamification']);

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
            
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token invalid'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Refresh a token
     *
     * @return \Illuminate\Http\Response
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            $user = Auth::user();

            Log::info('Token refreshed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            return $this->respondWithToken($token, $user);
            
        } catch (JWTException $e) {
            Log::warning('Token refresh failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::user()?->id,
            ]);
            
            return response()->json([
                'error' => 'Could not refresh token',
                'message' => 'Please login again'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @return \Illuminate\Http\Response
     */
    public function logout()
    {
        try {
            $user = Auth::user();
            JWTAuth::invalidate(JWTAuth::getToken());

            Log::info('User logged out', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
            
        } catch (JWTException $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::user()?->id,
            ]);
            
            return response()->json([
                'error' => 'Could not log out',
                'message' => 'Please try again'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the token array structure
     *
     * @param  string $token
     * @param  \App\Models\User $user
     * @param  int $status
     * @return \Illuminate\Http\Response
     */
    protected function respondWithToken($token, $user, $status = Response::HTTP_OK)
    {
        $ttl = JWTAuth::factory()->getTTL() * 60; // TTL in seconds
        
        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $ttl,
                'expires_at' => Carbon::now()->addSeconds($ttl)->toISOString(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'organization_id' => $user->organization_id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ]
        ], $status);
    }
}