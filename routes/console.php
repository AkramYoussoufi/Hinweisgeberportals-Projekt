<?php

use App\Jobs\CleanupInactiveAnonymousAccounts;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CleanupInactiveAnonymousAccounts())->dailyAt('02:00');
