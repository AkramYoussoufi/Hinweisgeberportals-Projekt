<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
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

    private function createReport(User $user): Report
    {
        return Report::create([
            'user_id'          => $user->id,
            'reference_number' => 'HIN-2026-TEST',
            'category'         => 'fraud',
            'status'           => 'received',
            'subject'          => 'Test subject',
            'description'      => 'Test description long enough',
            'is_anonymous'     => false,
        ]);
    }

    public function test_user_can_upload_attachment(): void
    {
        Storage::fake('local');

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        $file = UploadedFile::fake()->image('evidence.png', 100, 100);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/reports/HIN-2026-TEST/attachments', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'id',
                'original_filename',
                'mime_type',
                'size',
            ]);

        $this->assertDatabaseHas('attachments', [
            'report_id'         => $report->id,
            'original_filename' => 'evidence.png',
        ]);
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('local');

        $user  = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;
        $this->createReport($user);

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->post('/api/reports/HIN-2026-TEST/attachments', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_user_can_list_attachments(): void
    {
        Storage::fake('local');

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        Attachment::create([
            'report_id'         => $report->id,
            'original_filename' => 'evidence.png',
            'stored_filename'   => 'uuid-stored.png',
            'mime_type'         => 'image/png',
            'size'              => 1024,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/reports/HIN-2026-TEST/attachments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'attachments' => [
                    '*' => ['id', 'original_filename', 'mime_type', 'size'],
                ],
            ]);

        $this->assertCount(1, $response->json('attachments'));
    }

    public function test_user_can_download_own_attachment(): void
    {
        Storage::fake('local');

        $user   = $this->createUser();
        $token  = $user->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        $fakeFile = UploadedFile::fake()->image('evidence.png');
        Storage::disk('local')->putFileAs(
            'attachments/' . $report->id,
            $fakeFile,
            'stored-uuid.png'
        );

        $attachment = Attachment::create([
            'report_id'         => $report->id,
            'original_filename' => 'evidence.png',
            'stored_filename'   => 'stored-uuid.png',
            'mime_type'         => 'image/png',
            'size'              => 1024,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('/api/attachments/' . $attachment->id . '/download');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_admin_can_download_any_attachment(): void
    {
        Storage::fake('local');

        $admin  = $this->createAdmin();
        $user   = $this->createUser();
        $token  = $admin->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($user);

        $fakeFile = UploadedFile::fake()->image('evidence.png');
        Storage::disk('local')->putFileAs(
            'attachments/' . $report->id,
            $fakeFile,
            'stored-uuid.png'
        );

        $attachment = Attachment::create([
            'report_id'         => $report->id,
            'original_filename' => 'evidence.png',
            'stored_filename'   => 'stored-uuid.png',
            'mime_type'         => 'image/png',
            'size'              => 1024,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('/api/attachments/' . $attachment->id . '/download');

        $response->assertStatus(200);
    }

    public function test_user_cannot_download_another_users_attachment(): void
    {
        Storage::fake('local');

        $userA  = $this->createUser();
        $userB  = User::create([
            'email'        => 'other@test.com',
            'email_hash'   => hash('sha256', 'other@test.com'),
            'password'     => 'password123',
            'role'         => 'user',
            'is_anonymous' => false,
        ]);

        $tokenA = $userA->createToken('auth_token')->plainTextToken;
        $report = $this->createReport($userB);

        $attachment = Attachment::create([
            'report_id'         => $report->id,
            'original_filename' => 'evidence.png',
            'stored_filename'   => 'stored-uuid.png',
            'mime_type'         => 'image/png',
            'size'              => 1024,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenA,
        ])->get('/api/attachments/' . $attachment->id . '/download');

        $response->assertStatus(403);
    }

    public function test_upload_is_logged_in_audit(): void
    {
        Storage::fake('local');

        $user  = $this->createUser();
        $token = $user->createToken('auth_token')->plainTextToken;
        $this->createReport($user);

        $file = UploadedFile::fake()->image('evidence.png');

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('/api/reports/HIN-2026-TEST/attachments', [
            'file' => $file,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attachment_uploaded',
        ]);
    }
}
