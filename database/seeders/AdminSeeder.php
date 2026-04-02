<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'superadmin@hinweisgeberporal.de');
        $password = env('SUPERADMIN_PASSWORD', 'superadmin123456');

        User::updateOrCreate(
            ['email' => $email],
            [
                'email_hash'        => hash('sha256', $email),
                'password'          => Hash::make($password),
                'role'              => 'superadmin',
                'is_anonymous'      => false,
                'email_verified_at' => now(),
            ]
        );
    }
}
