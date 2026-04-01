<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
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
            'subject'          => 'Test subject',
            'description'      => 'Test description long enough',
            'is_anonymous'     => false,
        ], $overrides));
    }

    public function test_whistleblower_can_send_message(): void
    {
        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/reports/HIN-2026-TEST/messages', [
            'body' => 'This is my message to the admin',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'id', 'created_at']);

        $this->assertDatabaseHas('messages', [
            'report_id'   => $report->id,
            'sender_role' => 'whistleblower',
            'body'        => 'This is my message to the admin',
        ]);
    }

    public function test_admin_can_send_message(): void
    {
        $admin  = $this->createAdmin();
        $user   = $this->createUser();
        $token  = $admin->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/admin/reports/HIN-2026-TEST/messages', [
            'body' => 'This is the admin response',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'report_id'   => $report->id,
            'sender_role' => 'admin',
        ]);
    }

    public function test_whistleblower_can_read_messages(): void
    {
        $user   = $this->createUser();
        $admin  = $this->createAdmin();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        Message::create([
            'report_id'   => $report->id,
            'sender_id'   => $admin->id,
            'sender_role' => 'admin',
            'body'        => 'Admin message here',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports/HIN-2026-TEST/messages');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'messages' => [
                    '*' => ['id', 'sender_role', 'body', 'read_at', 'created_at'],
                ],
            ]);

        $this->assertCount(1, $response->json('messages'));
    }

    public function test_reading_messages_marks_other_side_as_read(): void
    {
        $user   = $this->createUser();
        $admin  = $this->createAdmin();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        $message = Message::create([
            'report_id'   => $report->id,
            'sender_id'   => $admin->id,
            'sender_role' => 'admin',
            'body'        => 'Admin message',
        ]);

        $this->assertNull($message->read_at);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports/HIN-2026-TEST/messages');

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_cannot_send_message_on_closed_report(): void
    {
        $user  = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->createReport($user, ['status' => 'closed']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/reports/HIN-2026-TEST/messages', [
            'body' => 'Trying to message on closed report',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot send messages on a closed report']);
    }

    public function test_whistleblower_cannot_read_another_users_messages(): void
    {
        $userA  = $this->createUser();
        $userB  = User::create([
            'email'        => 'other@test.com',
            'email_hash'   => hash('sha256', 'other@test.com'),
            'password'     => 'password123',
            'role'         => 'user',
            'is_anonymous' => false,
        ]);

        $this->createReport($userB);
        $tokenA = $userA->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenA,
        ])->getJson('/api/reports/HIN-2026-TEST/messages');

        $response->assertStatus(404);
    }

    public function test_message_does_not_expose_sender_identity(): void
    {
        $user   = $this->createUser();
        $admin  = $this->createAdmin();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        Message::create([
            'report_id'   => $report->id,
            'sender_id'   => $admin->id,
            'sender_role' => 'admin',
            'body'        => 'Admin message',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports/HIN-2026-TEST/messages');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('sender_id', $response->json('messages.0'));
    }
}
