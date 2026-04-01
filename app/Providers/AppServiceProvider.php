<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
