<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ResetPasswordNotification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::create([
            'email'             => 'user@test.com',
            'email_hash'        => hash('sha256', 'user@test.com'),
            'password'          => 'password123',
            'role'              => 'user',
            'is_anonymous'      => false,
            'email_verified_at' => now(),
        ]);
    }

    public function test_forgot_password_returns_success_for_existing_email(): void
    {
        Notification::fake();

        $this->createUser();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@test.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'If this email exists in our system you will receive a reset link shortly.',
            ]);
    }

    public function test_forgot_password_returns_same_message_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@test.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'If this email exists in our system you will receive a reset link shortly.',
            ]);
    }

    public function test_forgot_password_sends_notification(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@test.com',
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_anonymous_user_cannot_request_password_reset(): void
    {
        Notification::fake();

        User::create([
            'is_anonymous'  => true,
            'anon_token'    => 'test-token',
            'anon_pin_hash' => '123456',
            'role'          => 'user',
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'anon@test.com',
        ]);

        $response->assertStatus(200);
        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user  = $this->createUser();
        $token = app('auth.password.broker')->createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'user@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password reset successfully.']);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'user@test.com',
            'token'                 => 'invalid-token',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired token.']);
    }

    public function test_password_reset_requires_confirmed_password(): void
    {
        Notification::fake();

        $user  = $this->createUser();
        $token = app('auth.password.broker')->createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'user@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_new_password_after_reset(): void
    {
        Notification::fake();

        $user  = $this->createUser();
        $token = app('auth.password.broker')->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => 'user@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@test.com',
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);
    }
}
