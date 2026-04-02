<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->timestamps();
        });

        DB::table('portal_settings')->insert([
            ['key' => 'max_reports_per_hour_per_ip', 'value' => '5',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'max_file_size_mb',            'value' => '10', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'max_upload_per_week_mb',      'value' => '50', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_settings');
    }
};
