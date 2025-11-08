<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Worker;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    /**
     * Apply JWT middleware except for login.
     */

    /**
     * @method void middleware(array|string $middleware, array $options = [])
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Handle worker login and return JWT token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('username', 'password');

        /** @var JWTGuard $auth */
        $auth = auth('api');

        if (!$token = $auth->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password',
            ], 401);
        }

        /** @var Worker $worker */
        $worker = $auth->user();

        if ($worker->status !== 'active') {
            $auth->logout();
            return response()->json([
                'success' => false,
                'message' => 'Worker account is inactive',
            ], 403);
        }

        if (!$worker->station || $worker->station->status !== 'active') {
            $auth->logout();
            return response()->json([
                'success' => false,
                'message' => 'Assigned station is inactive',
            ], 403);
        }

        $worker->update(['last_login' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $auth->factory()->getTTL() * 60,
                'worker' => [
                    'id' => $worker->id,
                    'name' => $worker->name,
                    'username' => $worker->username,
                    'role' => $worker->role,
                    'station' => [
                        'id' => $worker->station->id,
                        'name' => $worker->station->name,
                        'location' => $worker->station->location,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get authenticated worker profile.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        /** @var Worker $worker */
        $worker = auth('api')->user();

        return response()->json([
            'success' => true,
            'data' => [
                'worker' => [
                    'id' => $worker->id,
                    'name' => $worker->name,
                    'username' => $worker->username,
                    'phone' => $worker->phone,
                    'role' => $worker->role,
                    'last_login' => $worker->last_login,
                    'station' => [
                        'id' => $worker->station->id,
                        'name' => $worker->station->name,
                        'location' => $worker->station->location,
                        'organization' => $worker->station->organization->name,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Logout and invalidate JWT token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        /** @var JWTGuard $auth */
        $auth = auth('api');
        $auth->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Refresh an expired JWT token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        /** @var JWTGuard $auth */
        $auth = auth('api');
        $token = $auth->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $auth->factory()->getTTL() * 60,
            ],
        ]);
    }
}
