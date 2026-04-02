<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'superadmin', 'deactivated_admin') DEFAULT 'user'");
    }

    public function down(): void
    {
        // Move any deactivated_admin back to user before shrinking the enum
        DB::statement("UPDATE users SET role = 'user' WHERE role = 'deactivated_admin'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'superadmin') DEFAULT 'user'");
    }
};
