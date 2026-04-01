<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::create([
            'email'        => 'test@example.com',
            'email_hash'   => hash('sha256', 'test@example.com'),
            'password'     => 'password123',
            'role'         => 'user',
            'is_anonymous' => false,
        ]);
    }

    private function createAnonymousUser(): User
    {
        return User::create([
            'is_anonymous'  => true,
            'anon_token'    => 'test-anon-token',
            'anon_pin_hash' => '123456',
            'role'          => 'user',
        ]);
    }

    private function validReportData(): array
    {
        return [
            'category'          => 'fraud',
            'subject'           => 'Test report subject',
            'description'       => 'This is a detailed description of the incident that occurred',
            'incident_date'     => '2026-03-01',
            'incident_location' => 'Berlin office',
            'involved_persons'  => 'John Doe',
        ];
    }

    public function test_registered_user_can_submit_report(): void
    {
        $user  = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/reports', $this->validReportData());

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'reference_number'])
            ->assertJsonMissing(['anonymous_access']);

        $this->assertDatabaseHas('reports', [
            'user_id'      => $user->id,
            'category'     => 'fraud',
            'status'       => 'received',
            'is_anonymous' => 0,
        ]);
    }

    public function test_anonymous_user_can_submit_report(): void
    {
        $response = $this->postJson('/api/reports', $this->validReportData());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'reference_number',
                'anonymous_access' => ['token', 'pin', 'warning'],
            ]);

        $this->assertDatabaseHas('reports', [
            'category'     => 'fraud',
            'status'       => 'received',
            'is_anonymous' => 1,
        ]);
    }

    public function test_report_submission_requires_category(): void
    {
        $data = $this->validReportData();
        unset($data['category']);

        $response = $this->postJson('/api/reports', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_report_submission_requires_valid_category(): void
    {
        $data             = $this->validReportData();
        $data['category'] = 'invalid_category';

        $response = $this->postJson('/api/reports', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_report_submission_requires_description_of_minimum_length(): void
    {
        $data                = $this->validReportData();
        $data['description'] = 'Too short';

        $response = $this->postJson('/api/reports', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_registered_user_can_list_own_reports(): void
    {
        $user  = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        Report::create([
            'user_id'          => $user->id,
            'reference_number' => 'HIN-2026-TEST',
            'category'         => 'fraud',
            'status'           => 'received',
            'subject'          => 'Test subject',
            'description'      => 'Test description long enough',
            'is_anonymous'     => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'reports' => [
                    '*' => ['reference_number', 'category', 'status', 'subject'],
                ],
            ]);

        $this->assertCount(1, $response->json('reports'));
    }

    public function test_user_cannot_see_other_users_reports(): void
    {
        $userA  = $this->createUser();
        $userB  = User::create([
            'email'        => 'other@example.com',
            'email_hash'   => hash('sha256', 'other@example.com'),
            'password'     => 'password123',
            'role'         => 'user',
            'is_anonymous' => false,
        ]);

        Report::create([
            'user_id'          => $userB->id,
            'reference_number' => 'HIN-2026-OTHER',
            'category'         => 'fraud',
            'status'           => 'received',
            'subject'          => 'Other user report',
            'description'      => 'This belongs to another user',
            'is_anonymous'     => false,
        ]);

        $token = $userA->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports/HIN-2026-OTHER');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_list_reports(): void
    {
        $response = $this->getJson('/api/reports');

        $response->assertStatus(401);
    }

    public function test_audit_log_is_created_on_report_submission(): void
    {
        $response = $this->postJson('/api/reports', $this->validReportData());

        $response->assertStatus(201);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'report_submitted',
        ]);
    }
}
