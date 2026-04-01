<?php

namespace Tests\Feature;

use App\Jobs\CleanupInactiveAnonymousAccounts;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupJobTest extends TestCase
{
    use RefreshDatabase;

    private function createAnonymousUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'is_anonymous'  => true,
            'anon_token'    => 'test-token-' . uniqid(),
            'anon_pin_hash' => '123456',
            'role'          => 'user',
            'last_active_at' => now()->subDays(31),
        ], $overrides));
    }

    private function createReport(User $user, array $overrides = []): Report
    {
        return Report::create(array_merge([
            'user_id'          => $user->id,
            'reference_number' => 'HIN-2026-' . uniqid(),
            'category'         => 'fraud',
            'status'           => 'closed',
            'subject'          => 'Test subject',
            'description'      => 'Test description',
            'is_anonymous'     => true,
            'closed_at'        => now()->subDays(361),
        ], $overrides));
    }

    public function test_inactive_anonymous_account_with_closed_reports_is_deleted(): void
    {
        Storage::fake('local');

        $user = $this->createAnonymousUser();
        $this->createReport($user);

        $this->assertDatabaseCount('users', 1);

        (new CleanupInactiveAnonymousAccounts())->handle();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_account_with_open_report_is_not_deleted(): void
    {
        $user = $this->createAnonymousUser();
        $this->createReport($user, ['status' => 'received', 'closed_at' => null]);

        (new CleanupInactiveAnonymousAccounts())->handle();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_account_with_recently_closed_report_is_not_deleted(): void
    {
        $user = $this->createAnonymousUser();
        $this->createReport($user, [
            'status'    => 'closed',
            'closed_at' => now()->subDays(10),
        ]);

        (new CleanupInactiveAnonymousAccounts())->handle();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_recently_active_account_is_not_deleted(): void
    {
        $user = $this->createAnonymousUser([
            'last_active_at' => now()->subDays(5),
        ]);
        $this->createReport($user);

        (new CleanupInactiveAnonymousAccounts())->handle();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registered_user_is_never_deleted(): void
    {
        User::create([
            'email'          => 'user@test.com',
            'email_hash'     => hash('sha256', 'user@test.com'),
            'password'       => 'password123',
            'role'           => 'user',
            'is_anonymous'   => false,
            'last_active_at' => now()->subDays(200),
        ]);

        (new CleanupInactiveAnonymousAccounts())->handle();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_account_with_no_reports_and_inactive_is_deleted(): void
    {
        $this->createAnonymousUser();

        (new CleanupInactiveAnonymousAccounts())->handle();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_only_eligible_accounts_are_deleted(): void
    {
        Storage::fake('local');

        $eligibleUser = $this->createAnonymousUser();
        $this->createReport($eligibleUser);

        $ineligibleUser = $this->createAnonymousUser([
            'last_active_at' => now()->subDays(5),
        ]);
        $this->createReport($ineligibleUser);

        (new CleanupInactiveAnonymousAccounts())->handle();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $ineligibleUser->id]);
    }
}
