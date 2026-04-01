<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupInactiveAnonymousAccounts implements ShouldQueue
{
    use Queueable;

    public int $inactiveDays      = 30;
    public int $retentionDays     = 360;

    public function handle(): void
    {
        $inactiveThreshold   = now()->subDays($this->inactiveDays);
        $retentionThreshold  = now()->subDays($this->retentionDays);

        $candidates = User::where('is_anonymous', true)
            ->where(function ($query) use ($inactiveThreshold) {
                $query->where('last_active_at', '<=', $inactiveThreshold)
                    ->orWhereNull('last_active_at');
            })
            ->get();

        $deleted = 0;

        foreach ($candidates as $user) {
            if ($this->isSafeToDelete($user, $retentionThreshold)) {
                DB::transaction(function () use ($user) {
                    $user->reports()->each(function (Report $report) {
                        $report->delete();
                    });
                    $user->delete();
                });
                $deleted++;
            }
        }

        Log::info("CleanupJob: {$deleted} anonymous accounts deleted.");
    }

    private function isSafeToDelete(User $user, $retentionThreshold): bool
    {
        $reports = $user->reports;

        if ($reports->isEmpty()) {
            return true;
        }

        foreach ($reports as $report) {
            if ($report->status !== 'closed') {
                return false;
            }

            if ($report->closed_at === null || $report->closed_at->gt($retentionThreshold)) {
                return false;
            }
        }

        return true;
    }
}
