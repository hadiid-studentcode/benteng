<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('sensor', 32)->index();
            $table->string('client_ip', 45)->index();
            $table->string('jenis', 20);          // beaconing | dga | ...
            $table->string('tingkat', 10);        // kritis | tinggi | ...
            $table->unsignedSmallInteger('skor');
            $table->string('judul');
            $table->string('attck', 20)->nullable();
            $table->text('bukti')->nullable();
            $table->string('status', 16)->default('baru'); // baru | dikarantina | false_positive
            $table->string('sidik')->unique();    // mencegah insiden dobel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
