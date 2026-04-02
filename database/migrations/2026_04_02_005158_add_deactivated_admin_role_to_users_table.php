<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'superadmin', 'deactivated_admin') DEFAULT 'user'");
        } else {
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE users SET role = 'user' WHERE role = 'deactivated_admin'");
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'superadmin') DEFAULT 'user'");
        } else {
            DB::statement("UPDATE users SET role = 'user' WHERE role = 'deactivated_admin'");
        }
    }
};
