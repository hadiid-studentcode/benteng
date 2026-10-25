<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            // Waktu bukti pertama (query/perangkat pertama terlihat), untuk menghitung waktu deteksi.
            $table->dateTime('mulai_pada')->nullable()->after('bukti');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn('mulai_pada');
        });
    }
};
