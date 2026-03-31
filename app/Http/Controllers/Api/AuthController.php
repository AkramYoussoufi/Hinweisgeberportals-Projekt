<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'email'        => $validated['email'],
            'email_hash'   => hash('sha256', strtolower(trim($validated['email']))),
            'password'     => $validated['password'],
            'role'         => 'user',
            'is_anonymous' => false,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'token'   => $token,
            'user'    => [
                'id'   => $user->id,
                'role' => $user->role,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email_hash', hash('sha256', strtolower(trim($validated['email']))))->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user->update(['last_active_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => [
                'id'   => $user->id,
                'role' => $user->role,
            ],
        ], 200);
    }

    public function anonymousLogin(Request $request)
    {
        $validated = $request->validate([
            'anon_token' => 'required|string',
            'pin'        => 'required|string',
        ]);

        $user = User::where('anon_token', $validated['anon_token'])
            ->where('is_anonymous', true)
            ->first();

        if (!$user || !Hash::check($validated['pin'], $user->anon_pin_hash)) {
            return response()->json([
                'message' => 'Invalid token or PIN',
            ], 401);
        }

        $user->update(['last_active_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => [
                'id'   => $user->id,
                'role' => $user->role,
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    public function me(Request $request)
    {
        return response()->json([
            'id'           => $request->user()->id,
            'role'         => $request->user()->role,
            'is_anonymous' => $request->user()->is_anonymous,
        ], 200);
    }
}
