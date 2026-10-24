<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_events', function (Blueprint $table) {
            $table->id();
            $table->string('sensor', 32)->index();
            $table->dateTime('queried_at')->index();
            $table->string('qtype', 16);
            $table->string('domain', 255)->index();
            $table->string('client_ip', 45)->index();
            // Sidik jari baris log asli; mencegah data dobel saat sensor mengirim ulang.
            $table->char('line_hash', 40)->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_events');
    }
};