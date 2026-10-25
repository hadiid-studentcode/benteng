<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perangkat yang mendapat alamat dari DHCP sensor (/tmp/dhcp.leases).
        Schema::create('perangkat', function (Blueprint $table) {
            $table->id();
            $table->string('sensor', 32);
            $table->string('mac', 17);
            $table->string('ip', 45)->index();
            $table->string('hostname', 64)->nullable();
            $table->boolean('dikenal')->default(false); // true = perangkat sah satuan
            $table->dateTime('pertama_terlihat');
            $table->dateTime('terakhir_terlihat');
            $table->timestamps();
            $table->unique(['sensor', 'mac']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perangkat');
    }
};
