<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Str;

class ReportService
{
    public function generateReferenceNumber(): string
    {
        do {
            $year      = date('Y');
            $random    = strtoupper(Str::random(4));
            $reference = "HIN-{$year}-{$random}";
        } while (Report::where('reference_number', $reference)->exists());

        return $reference;
    }

    public function createAnonymousUser(): array
    {
        $token    = Str::uuid()->toString();
        $pin      = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user = User::create([
            'is_anonymous'  => true,
            'anon_token'    => $token,
            'anon_pin_hash' => $pin,
            'role'          => 'user',
        ]);

        return [
            'user' => $user,
            'token' => $token,
            'pin'   => $pin,
        ];
    }

    public function logAction(string $reportId, string $actorId, string $action, ?array $oldValue = null, ?array $newValue = null, ?string $ip = null): void
    {
        AuditLog::create([
            'report_id' => $reportId,
            'actor_id'  => $actorId,
            'action'    => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => $ip,
        ]);
    }
}
