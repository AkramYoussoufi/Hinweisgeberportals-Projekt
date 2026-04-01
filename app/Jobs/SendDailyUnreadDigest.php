<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\User;
use App\Notifications\DailyUnreadDigestNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDailyUnreadDigest implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $admins = User::whereIn('role', ['admin', 'superadmin'])
            ->whereNotNull('email')
            ->get();

        foreach ($admins as $admin) {
            $reportsWithUnread = $this->getReportsWithUnreadForAdmin($admin);

            if (empty($reportsWithUnread)) {
                continue;
            }

            $admin->notify(new DailyUnreadDigestNotification($reportsWithUnread));

            Log::info("DigestJob: Sent digest to admin {$admin->id} with " . count($reportsWithUnread) . " reports.");
        }
    }

    private function getReportsWithUnreadForAdmin(User $admin): array
    {
        $reportIds = Message::where('sender_id', $admin->id)
            ->distinct()
            ->pluck('report_id');

        if ($reportIds->isEmpty()) {
            return [];
        }

        $result = [];

        foreach ($reportIds as $reportId) {
            $unreadCount = Message::where('report_id', $reportId)
                ->where('sender_role', 'whistleblower')
                ->whereNull('read_at')
                ->count();

            if ($unreadCount > 0) {
                $report = \App\Models\Report::find($reportId);
                if ($report && $report->status !== 'closed') {
                    $result[] = [
                        'reference_number' => $report->reference_number,
                        'subject'          => $report->subject,
                        'unread_count'     => $unreadCount,
                    ];
                }
            }
        }

        return $result;
    }
}
