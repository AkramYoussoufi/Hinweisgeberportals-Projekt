<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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

    private function validReportData(): array
    {
        return [
            'category'    => 'fraud',
            'subject'     => 'Test subject',
            'description' => 'This is a detailed description of the incident that occurred',
        ];
    }

    /** Submit as an authenticated user (no hCaptcha needed) */
    private function submitAsUser(User $user): \Illuminate\Testing\TestResponse
    {
        $token = $user->createToken('t')->plainTextToken;

        return $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/reports', $this->validReportData());
    }

    public function test_requests_within_limit_succeed(): void
    {
        PortalSetting::where('key', 'max_reports_per_hour_per_ip')->update(['value' => '3']);

        $userA = $this->createVerifiedUser();

        $this->submitAsUser($userA)->assertStatus(201);
        $this->submitAsUser($userA)->assertStatus(201);
        $this->submitAsUser($userA)->assertStatus(201);
    }

    public function test_request_exceeding_limit_is_rejected(): void
    {
        PortalSetting::where('key', 'max_reports_per_hour_per_ip')->update(['value' => '2']);

        $user = $this->createVerifiedUser();

        $this->submitAsUser($user)->assertStatus(201);
        $this->submitAsUser($user)->assertStatus(201);
        $this->submitAsUser($user)->assertStatus(429);
    }

    public function test_rate_limit_response_contains_correct_message(): void
    {
        PortalSetting::where('key', 'max_reports_per_hour_per_ip')->update(['value' => '1']);

        $user = $this->createVerifiedUser();

        $this->submitAsUser($user)->assertStatus(201);
        $this->submitAsUser($user)
            ->assertStatus(429)
            ->assertJson(['message' => 'Too many submissions. Please try again later.']);
    }

    public function test_reduced_limit_takes_effect_immediately(): void
    {
        PortalSetting::where('key', 'max_reports_per_hour_per_ip')->update(['value' => '5']);

        $user = $this->createVerifiedUser();
        $this->submitAsUser($user)->assertStatus(201);
        PortalSetting::where('key', 'max_reports_per_hour_per_ip')->update(['value' => '1']);

        $this->submitAsUser($user)->assertStatus(429);
    }
}
