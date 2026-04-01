<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
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
            'email'        => 'user@test.com',
            'email_hash'   => hash('sha256', 'user@test.com'),
            'password'     => 'password123',
            'role'         => 'user',
            'is_anonymous' => false,
        ]);
    }

    private function createReport(User $user, array $overrides = []): Report
    {
        return Report::create(array_merge([
            'user_id'          => $user->id,
            'reference_number' => 'HIN-2026-TEST',
            'category'         => 'fraud',
            'status'           => 'received',
            'subject'          => 'Test report subject',
            'description'      => 'Test description long enough',
            'is_anonymous'     => false,
        ], $overrides));
    }

    public function test_admin_can_list_all_reports(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'reports' => [
                    '*' => ['reference_number', 'category', 'status', 'subject', 'is_anonymous'],
                ],
            ]);

        $this->assertCount(1, $response->json('reports'));
    }

    public function test_admin_can_filter_reports_by_status(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user, ['reference_number' => 'HIN-2026-AAA', 'status' => 'received']);
        $this->createReport($user, ['reference_number' => 'HIN-2026-BBB', 'status' => 'closed']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/reports?status=received');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('reports'));
        $this->assertEquals('received', $response->json('reports.0.status'));
    }

    public function test_admin_can_view_single_report(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/reports/HIN-2026-TEST');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'reference_number',
                'category',
                'status',
                'subject',
                'description',
            ]);
    }

    public function test_admin_can_update_report_status(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/admin/reports/HIN-2026-TEST/status', [
            'status' => 'reviewing',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Status updated successfully',
                'status'  => 'reviewing',
            ]);

        $this->assertDatabaseHas('reports', [
            'reference_number' => 'HIN-2026-TEST',
            'status'           => 'reviewing',
        ]);
    }

    public function test_closing_report_sets_closed_at(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/admin/reports/HIN-2026-TEST/status', [
            'status' => 'closed',
        ]);

        $report = Report::where('reference_number', 'HIN-2026-TEST')->first();
        $this->assertNotNull($report->closed_at);
    }

    public function test_admin_cannot_set_invalid_status(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/admin/reports/HIN-2026-TEST/status', [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_regular_user_cannot_access_admin_routes(): void
    {
        $user  = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/reports');

        $response->assertStatus(403);
    }

    public function test_status_change_is_logged_in_audit(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/admin/reports/HIN-2026-TEST/status', [
            'status' => 'reviewing',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'status_changed',
        ]);
    }

    public function test_report_view_is_logged_in_audit(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $this->createReport($user);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/reports/HIN-2026-TEST');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'report_viewed',
        ]);
    }
}
