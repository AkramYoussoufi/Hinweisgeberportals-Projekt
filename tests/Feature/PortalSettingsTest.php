<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::create([
            'email'        => 'superadmin@test.com',
            'email_hash'   => hash('sha256', 'superadmin@test.com'),
            'password'     => 'password123',
            'role'         => 'superadmin',
            'is_anonymous' => false,
        ]);
    }

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

    // ── GET /superadmin/settings ──────────────────────────────────────────────

    public function test_superadmin_can_read_settings(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/superadmin/settings')
            ->assertStatus(200)
            ->assertJsonStructure([
                'settings' => [
                    'max_reports_per_hour_per_ip',
                    'max_file_size_mb',
                    'max_upload_per_week_mb',
                ],
            ]);
    }

    public function test_settings_response_contains_seeded_defaults(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/superadmin/settings')
            ->assertStatus(200)
            ->assertJson([
                'settings' => [
                    'max_reports_per_hour_per_ip' => '5',
                    'max_file_size_mb'            => '10',
                    'max_upload_per_week_mb'      => '50',
                ],
            ]);
    }

    public function test_admin_cannot_read_settings(): void
    {
        $token = $this->createAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/superadmin/settings')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_read_settings(): void
    {
        $this->getJson('/api/superadmin/settings')
            ->assertStatus(401);
    }

    // ── PATCH /superadmin/settings ────────────────────────────────────────────

    public function test_superadmin_can_update_settings(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patchJson('/api/superadmin/settings', [
                'max_reports_per_hour_per_ip' => 10,
                'max_file_size_mb'            => 20,
                'max_upload_per_week_mb'      => 100,
            ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Settings updated successfully.']);

        $this->assertDatabaseHas('portal_settings', ['key' => 'max_reports_per_hour_per_ip', 'value' => '10']);
        $this->assertDatabaseHas('portal_settings', ['key' => 'max_file_size_mb',            'value' => '20']);
        $this->assertDatabaseHas('portal_settings', ['key' => 'max_upload_per_week_mb',      'value' => '100']);
    }

    public function test_updated_values_are_reflected_in_subsequent_get(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;
        $auth  = ['Authorization' => 'Bearer ' . $token];

        $this->withHeaders($auth)->patchJson('/api/superadmin/settings', [
            'max_reports_per_hour_per_ip' => 15,
            'max_file_size_mb'            => 25,
            'max_upload_per_week_mb'      => 75,
        ]);

        $this->withHeaders($auth)
            ->getJson('/api/superadmin/settings')
            ->assertStatus(200)
            ->assertJson([
                'settings' => [
                    'max_reports_per_hour_per_ip' => '15',
                    'max_file_size_mb'            => '25',
                    'max_upload_per_week_mb'      => '75',
                ],
            ]);
    }

    public function test_admin_cannot_update_settings(): void
    {
        $token = $this->createAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patchJson('/api/superadmin/settings', [
                'max_reports_per_hour_per_ip' => 10,
                'max_file_size_mb'            => 20,
                'max_upload_per_week_mb'      => 100,
            ])
            ->assertStatus(403);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_update_rejects_zero_values(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patchJson('/api/superadmin/settings', [
                'max_reports_per_hour_per_ip' => 0,
                'max_file_size_mb'            => 10,
                'max_upload_per_week_mb'      => 50,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_reports_per_hour_per_ip']);
    }

    public function test_update_rejects_values_exceeding_maximum(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patchJson('/api/superadmin/settings', [
                'max_reports_per_hour_per_ip' => 5,
                'max_file_size_mb'            => 501,   // max is 500
                'max_upload_per_week_mb'      => 50,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_file_size_mb']);
    }

    public function test_update_rejects_non_integer_values(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patchJson('/api/superadmin/settings', [
                'max_reports_per_hour_per_ip' => 'five',
                'max_file_size_mb'            => 10,
                'max_upload_per_week_mb'      => 50,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_reports_per_hour_per_ip']);
    }

    public function test_update_requires_all_three_fields(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patchJson('/api/superadmin/settings', [
                'max_reports_per_hour_per_ip' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_file_size_mb', 'max_upload_per_week_mb']);
    }

    public function test_update_persists_all_three_keys_independently(): void
    {
        $token = $this->createSuperAdmin()->createToken('t')->plainTextToken;
        $auth  = ['Authorization' => 'Bearer ' . $token];

        // First update
        $this->withHeaders($auth)->patchJson('/api/superadmin/settings', [
            'max_reports_per_hour_per_ip' => 8,
            'max_file_size_mb'            => 15,
            'max_upload_per_week_mb'      => 60,
        ]);

        // Second update changes only one value
        $this->withHeaders($auth)->patchJson('/api/superadmin/settings', [
            'max_reports_per_hour_per_ip' => 12,
            'max_file_size_mb'            => 15,
            'max_upload_per_week_mb'      => 60,
        ]);

        $this->assertEquals('12', PortalSetting::find('max_reports_per_hour_per_ip')->value);
        $this->assertEquals('15', PortalSetting::find('max_file_size_mb')->value);
        $this->assertEquals('60', PortalSetting::find('max_upload_per_week_mb')->value);
    }
}
