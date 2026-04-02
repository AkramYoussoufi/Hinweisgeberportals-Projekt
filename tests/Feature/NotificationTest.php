<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Notifications\NewReportNotification;
use App\Notifications\StatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'email'        => 'admin@test.com',
            'email_hash'   => hash('sha256', 'admin@test.com'),
            'password'     => 'password123',
            'role'         => 'admin',
            'is_anonymous' => false,
        ]);
    }

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

    private function createReport(User $user, array $overrides = []): Report
    {
        return Report::create(array_merge([
            'user_id'          => $user->id,
            'reference_number' => 'HIN-2026-TEST',
            'category'         => 'fraud',
            'status'           => 'received',
            'subject'          => 'Test subject',
            'description'      => 'Test description long enough',
            'is_anonymous'     => false,
        ], $overrides));
    }

    public function test_admins_are_notified_when_report_is_submitted(): void
    {
        Notification::fake();

        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/reports', [
            'category'    => 'fraud',
            'subject'     => 'Test report',
            'description' => 'This is a detailed description of the incident that occurred',
        ]);

        Notification::assertSentTo($admin, NewReportNotification::class);
    }

    public function test_whistleblower_is_notified_when_admin_sends_message(): void
    {
        Notification::fake();

        $admin = $this->createAdmin();
        $user  = $this->createUser();

        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->postJson('/api/admin/reports/HIN-2026-TEST/messages', [
            'body' => 'This is the admin response to your report',
        ]);

        Notification::assertSentTo($user, NewMessageNotification::class);
    }

    public function test_anonymous_whistleblower_is_not_notified(): void
    {
        Notification::fake();

        $admin = $this->createAdmin();
        $anon  = User::create([
            'is_anonymous'  => true,
            'anon_token'    => 'test-token',
            'anon_pin_hash' => '123456',
            'role'          => 'user',
        ]);

        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($anon, ['is_anonymous' => true]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->postJson('/api/admin/reports/HIN-2026-TEST/messages', [
            'body' => 'Admin message to anonymous user',
        ]);

        Notification::assertNotSentTo($anon, NewMessageNotification::class);
    }

    public function test_whistleblower_is_notified_when_status_changes(): void
    {
        Notification::fake();

        $admin      = $this->createAdmin();
        $user       = $this->createUser();
        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->patchJson('/api/admin/reports/HIN-2026-TEST/status', [
            'status' => 'reviewing',
        ]);

        Notification::assertSentTo($user, StatusChangedNotification::class);
    }

    public function test_anonymous_whistleblower_not_notified_on_status_change(): void
    {
        Notification::fake();

        $admin = $this->createAdmin();
        $anon  = User::create([
            'is_anonymous'  => true,
            'anon_token'    => 'test-token',
            'anon_pin_hash' => '123456',
            'role'          => 'user',
        ]);

        $adminToken = $admin->createToken('auth_token')->plainTextToken;
        $this->createReport($anon, ['is_anonymous' => true]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
        ])->patchJson('/api/admin/reports/HIN-2026-TEST/status', [
            'status' => 'reviewing',
        ]);

        Notification::assertNotSentTo($anon, StatusChangedNotification::class);
    }

    public function test_notification_does_not_block_report_submission(): void
    {
        $user  = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/reports', [
            'category'    => 'fraud',
            'subject'     => 'Test report',
            'description' => 'This is a detailed description of the incident that occurred',
        ]);

        $response->assertStatus(201);
    }
}
