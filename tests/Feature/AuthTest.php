<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'role'],
            ]);

        $this->assertDatabaseHas('users', [
            'role'         => 'user',
            'is_anonymous' => 0,
        ]);
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email'                 => 'not-an-email',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_confirmed_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login(): void
    {
        $user = User::create([
            'email'        => 'test@example.com',
            'email_hash'   => hash('sha256', 'test@example.com'),
            'password'     => 'password123',
            'role'         => 'user',
            'is_anonymous' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'role'],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'email'        => 'test@example.com',
            'email_hash'   => hash('sha256', 'test@example.com'),
            'password'     => 'password123',
            'role'         => 'user',
            'is_anonymous' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::create([
            'email'        => 'test@example.com',
            'email_hash'   => hash('sha256', 'test@example.com'),
            'password'     => 'password123',
            'role'         => 'user',
            'is_anonymous' => false,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_anonymous_login_works(): void
    {
        $pin  = '123456';
        $user = User::create([
            'is_anonymous'  => true,
            'anon_token'    => 'test-token-uuid',
            'anon_pin_hash' => $pin,
            'role'          => 'user',
        ]);

        $response = $this->postJson('/api/auth/anonymous-login', [
            'anon_token' => 'test-token-uuid',
            'pin'        => $pin,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'token', 'user']);
    }

    public function test_anonymous_login_fails_with_wrong_pin(): void
    {
        User::create([
            'is_anonymous'  => true,
            'anon_token'    => 'test-token-uuid',
            'anon_pin_hash' => '123456',
            'role'          => 'user',
        ]);

        $response = $this->postJson('/api/auth/anonymous-login', [
            'anon_token' => 'test-token-uuid',
            'pin'        => '999999',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid token or PIN']);
    }
}
