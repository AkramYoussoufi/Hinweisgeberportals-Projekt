<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

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

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Registration successful. Please verify your email before logging in.',
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

        if ($user->role === 'user' && !$user->is_anonymous && !$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address before logging in.',
            ], 403);
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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $emailHash = hash('sha256', strtolower(trim($request->email)));
        $user      = User::where('email_hash', $emailHash)
            ->where('is_anonymous', false)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'If this email exists in our system you will receive a reset link shortly.',
            ], 200);
        }

        $token = app('auth.password.broker')->createToken($user);

        $user->sendPasswordResetNotification($token);

        return response()->json([
            'message' => 'If this email exists in our system you will receive a reset link shortly.',
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|min:8|confirmed',
        ]);

        $emailHash = hash('sha256', strtolower(trim($validated['email'])));
        $user      = User::where('email_hash', $emailHash)
            ->where('is_anonymous', false)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Invalid token or email.',
            ], 422);
        }

        $status = app('auth.password.broker')->reset(
            [
                'email'                 => $validated['email'],
                'password'              => $validated['password'],
                'password_confirmation' => $validated['password'],
                'token'                 => $validated['token'],
            ],
            function (User $user, string $password) {
                $user->update(['password' => $password]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Invalid or expired token.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ], 200);
    }
}
