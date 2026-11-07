<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Domain yang diblokir di DNS sensor, berlaku untuk semua perangkat di jaringan sensor itu.
        Schema::create('domain_diblokir', function (Blueprint $table) {
            $table->id();
            $table->string('sensor', 32);
            $table->string('domain');
            $table->unsignedBigInteger('incident_id')->nullable();
            $table->string('pelaku', 64);
            $table->timestamp('diterapkan_pada')->nullable();
            $table->timestamps();
            $table->unique(['sensor', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_diblokir');
    }
};
