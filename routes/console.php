<?php

use App\Jobs\CleanupInactiveAnonymousAccounts;
use App\Jobs\SendDailyUnreadDigest;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CleanupInactiveAnonymousAccounts())->dailyAt('02:00');
Schedule::job(new SendDailyUnreadDigest())->dailyAt('08:00');
