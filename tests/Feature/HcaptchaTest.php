<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HcaptchaTest extends TestCase
{
    use RefreshDatabase;

    private function validReportData(array $overrides = []): array
    {
        return array_merge([
            'category'    => 'fraud',
            'subject'     => 'Test subject',
            'description' => 'This is a detailed description of the incident that occurred',
        ], $overrides);
    }

    private function createVerifiedUser(): User
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

    public function test_anonymous_submission_fails_without_captcha_token(): void
    {
        Http::fake(['hcaptcha.com/*' => Http::response(['success' => false], 200)]);

        $response = $this->postJson('/api/reports', $this->validReportData());

        $response->assertStatus(422)
            ->assertJson(['message' => 'Captcha verification failed. Please try again.']);
    }

    public function test_anonymous_submission_fails_with_invalid_captcha_token(): void
    {
        Http::fake(['hcaptcha.com/*' => Http::response(['success' => false], 200)]);

        $response = $this->postJson('/api/reports', $this->validReportData([
            'hcaptcha_token' => 'bad-token',
        ]));

        $response->assertStatus(422)
            ->assertJson(['message' => 'Captcha verification failed. Please try again.']);
    }

    public function test_anonymous_submission_succeeds_with_valid_captcha_token(): void
    {
        Http::fake(['hcaptcha.com/*' => Http::response(['success' => true], 200)]);

        $response = $this->postJson('/api/reports', $this->validReportData([
            'hcaptcha_token' => 'valid-token',
        ]));

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'reference_number',
                'anonymous_access' => ['token', 'pin', 'warning'],
            ]);
    }

    public function test_captcha_is_actually_called_for_anonymous_submission(): void
    {
        Http::fake(['hcaptcha.com/*' => Http::response(['success' => true], 200)]);

        $this->postJson('/api/reports', $this->validReportData([
            'hcaptcha_token' => 'valid-token',
        ]));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hcaptcha.com/siteverify')
                && $request['response'] === 'valid-token';
        });
    }

    public function test_registered_user_submission_bypasses_captcha(): void
    {
        Http::fake();

        $user  = $this->createVerifiedUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/reports', $this->validReportData());

        $response->assertStatus(201);

        Http::assertNothingSent();
    }

    public function test_captcha_failure_does_not_create_report_or_user(): void
    {
        Http::fake(['hcaptcha.com/*' => Http::response(['success' => false], 200)]);

        $this->postJson('/api/reports', $this->validReportData([
            'hcaptcha_token' => 'bad-token',
        ]));

        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
