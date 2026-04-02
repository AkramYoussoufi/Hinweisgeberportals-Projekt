<?php

namespace App\Providers;

use App\Models\User;
use App\Services\PortalSettings;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('report-submission', function (Request $request) {
            $max = PortalSettings::getInt('max_reports_per_hour_per_ip');
            return Limit::perHour($max)->by($request->ip())->response(function () {
                return response()->json(['message' => 'Too many submissions. Please try again later.'], 429);
            });
        });

        Auth::provider('encrypted-email', function ($app, array $config) {
            return new class($app['hash'], $config['model']) extends EloquentUserProvider {
                public function __construct(Hasher $hasher, string $model)
                {
                    parent::__construct($hasher, $model);
                }

                public function retrieveByCredentials(array $credentials): ?User
                {
                    if (!isset($credentials['email'])) {
                        return null;
                    }

                    $emailHash = hash('sha256', strtolower(trim($credentials['email'])));

                    return User::where('email_hash', $emailHash)
                        ->where('is_anonymous', false)
                        ->first();
                }
            };
        });
    }
}
