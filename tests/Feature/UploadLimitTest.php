<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\PortalSetting;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadLimitTest extends TestCase
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

    private function createReport(User $user, string $ref = 'HIN-2026-TEST'): Report
    {
        return Report::create([
            'user_id'          => $user->id,
            'reference_number' => $ref,
            'category'         => 'fraud',
            'status'           => 'received',
            'subject'          => 'Test subject',
            'description'      => 'Test description long enough',
            'is_anonymous'     => false,
        ]);
    }

    private function seedExistingUpload(Report $report, int $sizeBytes, \Carbon\Carbon $createdAt): void
    {
        Attachment::forceCreate([
            'report_id'         => $report->id,
            'original_filename' => 'existing.pdf',
            'stored_filename'   => 'existing.pdf',
            'mime_type'         => 'application/pdf',
            'size'              => $sizeBytes,
            'created_at'        => $createdAt,
            'updated_at'        => $createdAt,
        ]);
    }

    // ── Single-file size limit ────────────────────────────────────────────────

    public function test_file_within_single_file_limit_is_accepted(): void
    {
        Storage::fake('local');

        PortalSetting::where('key', 'max_file_size_mb')->update(['value' => '2']);

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $this->createReport($user);

        // 1 MB file — within the 2 MB limit
        $file = UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->post('/api/reports/HIN-2026-TEST/attachments', ['file' => $file])
            ->assertStatus(201);
    }

    public function test_file_exceeding_single_file_limit_is_rejected(): void
    {
        Storage::fake('local');

        PortalSetting::where('key', 'max_file_size_mb')->update(['value' => '1']);

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $this->createReport($user);

        // 1025 KB — exceeds the 1 MB (1024 KB) limit
        $file = UploadedFile::fake()->create('big.pdf', 1025, 'application/pdf');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->post('/api/reports/HIN-2026-TEST/attachments', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_single_file_limit_uses_configured_value(): void
    {
        Storage::fake('local');

        // Set limit to 5 MB — a 4 MB file should pass
        PortalSetting::where('key', 'max_file_size_mb')->update(['value' => '5']);

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $this->createReport($user);

        $file = UploadedFile::fake()->create('medium.pdf', 4096, 'application/pdf');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->post('/api/reports/HIN-2026-TEST/attachments', ['file' => $file])
            ->assertStatus(201);
    }

    // ── Weekly rolling limit ──────────────────────────────────────────────────

    public function test_upload_within_weekly_limit_is_accepted(): void
    {
        Storage::fake('local');

        PortalSetting::where('key', 'max_upload_per_week_mb')->update(['value' => '10']);

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        // 5 MB already used this week
        $this->seedExistingUpload($report, 5 * 1024 * 1024, now()->subDays(2));

        // Uploading another 4 MB stays within the 10 MB limit
        $file = UploadedFile::fake()->create('doc.pdf', 4096, 'application/pdf');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->post('/api/reports/HIN-2026-TEST/attachments', ['file' => $file])
            ->assertStatus(201);
    }

    public function test_upload_exceeding_weekly_limit_is_rejected(): void
    {
        Storage::fake('local');

        PortalSetting::where('key', 'max_upload_per_week_mb')->update(['value' => '1']);

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        // 900 KB already used within the last 7 days
        $this->seedExistingUpload($report, 900 * 1024, now()->subDays(3));

        // Trying to add another 200 KB would push total to ~1.1 MB, exceeding the 1 MB limit
        $file = UploadedFile::fake()->create('extra.pdf', 200, 'application/pdf');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->post('/api/reports/HIN-2026-TEST/attachments', ['file' => $file])
            ->assertStatus(422)
            ->assertJson(['message' => 'You have exceeded your 1MB weekly upload limit.']);
    }

    public function test_uploads_older_than_7_days_do_not_count_toward_weekly_limit(): void
    {
        Storage::fake('local');

        PortalSetting::where('key', 'max_upload_per_week_mb')->update(['value' => '1']);

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        // Large upload from 8 days ago — outside the rolling window
        $this->seedExistingUpload($report, 900 * 1024, now()->subDays(8));

        // 200 KB upload this week should succeed (only 200 KB counted in window)
        $file = UploadedFile::fake()->create('new.pdf', 200, 'application/pdf');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->post('/api/reports/HIN-2026-TEST/attachments', ['file' => $file])
            ->assertStatus(201);
    }

    public function test_weekly_limit_aggregates_across_multiple_reports(): void
    {
        Storage::fake('local');

        PortalSetting::where('key', 'max_upload_per_week_mb')->update(['value' => '1']);

        $user    = $this->createUser();
        $token   = $user->createToken('auth_token')->plainTextToken;
        $report1 = $this->createReport($user, 'HIN-2026-R1');
        $report2 = $this->createReport($user, 'HIN-2026-R2');

        // 700 KB already used on a different report this week
        $this->seedExistingUpload($report2, 700 * 1024, now()->subDays(1));

        // Attempting 400 KB on report1 would bring the total to ~1.1 MB — over the limit
        $file = UploadedFile::fake()->create('extra.pdf', 400, 'application/pdf');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->post('/api/reports/HIN-2026-R1/attachments', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_weekly_limit_is_per_account_not_shared(): void
    {
        Storage::fake('local');

        PortalSetting::where('key', 'max_upload_per_week_mb')->update(['value' => '1']);

        $userA = $this->createUser();
        $userB = User::create([
            'email'             => 'other@test.com',
            'email_hash'        => hash('sha256', 'other@test.com'),
            'password'          => 'password123',
            'role'              => 'user',
            'is_anonymous'      => false,
            'email_verified_at' => now(),
        ]);

        $reportA = $this->createReport($userA, 'HIN-2026-A');
        $reportB = $this->createReport($userB, 'HIN-2026-B');
        $tokenA  = $userA->createToken('t')->plainTextToken;

        // UserB has 900 KB used this week — should NOT affect userA's limit
        $this->seedExistingUpload($reportB, 900 * 1024, now()->subDays(1));

        $file = UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');

        $this->withHeaders(['Authorization' => 'Bearer ' . $tokenA])
            ->post('/api/reports/HIN-2026-A/attachments', ['file' => $file])
            ->assertStatus(201);
    }
}
