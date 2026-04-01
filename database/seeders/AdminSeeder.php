<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'email'        => 'admin@hinweisgeberporal.de',
            'email_hash'   => hash('sha256', 'admin@hinweisgeberporal.de'),
            'password'     => 'admin123456',
            'role'         => 'admin',
            'is_anonymous' => false,
        ]);

        User::create([
            'email'        => 'superadmin@hinweisgeberporal.de',
            'email_hash'   => hash('sha256', 'superadmin@hinweisgeberporal.de'),
            'password'     => 'superadmin123456',
            'role'         => 'superadmin',
            'is_anonymous' => false,
        ]);
    }
}
