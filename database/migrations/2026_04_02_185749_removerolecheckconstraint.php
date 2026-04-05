<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemoveRoleCheckConstraint extends Migration
{
    public function up()
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY COLUMN role VARCHAR(255) NOT NULL DEFAULT "user"');
        }
    }

    public function down()
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'superadmin', 'user'))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'superadmin', 'user') NOT NULL DEFAULT 'user'");
        }
    }
}
