<?php

namespace App\Http\Controllers;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\FitnessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => 'employee',
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user'         => $user,
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = JWTAuth::user();
        $refreshToken = $this->issueRefreshToken($user);

        return response()->json([
            'user'          => $user,
            'access_token'  => $token,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires_in'    => config('jwt.ttl') * 60,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);

        $hash  = hash('sha256', $request->refresh_token);
        $entry = RefreshToken::where('token_hash', $hash)->first();

        if (!$entry || !$entry->isValid()) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        $entry->update(['revoked_at' => now()]);

        $user  = $entry->user;
        $token = JWTAuth::fromUser($user);
        $refreshToken = $this->issueRefreshToken($user);

        return response()->json([
            'access_token'  => $token,
            'refresh_token' => $refreshToken,
            'token_type'    => 'bearer',
            'expires_in'    => config('jwt.ttl') * 60,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($rt = $request->input('refresh_token')) {
            $hash = hash('sha256', $rt);
            RefreshToken::where('token_hash', $hash)->update(['revoked_at' => now()]);
        }

        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(): JsonResponse
    {
        return response()->json(JWTAuth::user());
    }

    private function issueRefreshToken(User $user): string
    {
        $token = Str::random(64);
        RefreshToken::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);
        return $token;
    }
}
