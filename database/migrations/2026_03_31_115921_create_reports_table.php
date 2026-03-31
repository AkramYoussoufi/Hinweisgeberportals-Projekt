<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('reference_number')->unique();
            $table->enum('category', ['fraud', 'harassment', 'safety', 'discrimination', 'other']);
            $table->enum('status', ['received', 'reviewing', 'clarification', 'closed'])->default('received');
            $table->string('subject');
            $table->text('description');
            $table->date('incident_date')->nullable();
            $table->string('incident_location')->nullable();
            $table->text('involved_persons')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};