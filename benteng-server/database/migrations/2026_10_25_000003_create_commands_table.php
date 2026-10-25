<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commands', function (Blueprint $table) {
            $table->id();
            $table->string('sensor', 32)->index();
            $table->string('aksi', 20);            // quarantine | unquarantine
            $table->string('target_ip', 45);
            $table->unsignedBigInteger('incident_id')->nullable();
            $table->string('status', 16)->default('pending'); // pending | selesai | gagal
            $table->string('hasil', 16)->nullable();
            $table->timestamp('diambil_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('pelaku', 64);          // analis | sensor:BTG-02
            $table->string('aksi', 64);
            $table->string('target', 64)->nullable();
            $table->unsignedBigInteger('incident_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('commands');
    }
};
