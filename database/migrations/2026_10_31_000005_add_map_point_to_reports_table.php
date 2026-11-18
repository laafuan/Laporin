<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Titik lokasi kerusakan pada denah sekolah, dalam persen dari lebar/tinggi gambar (0–100).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->decimal('map_x', 5, 2)->nullable();
            $table->decimal('map_y', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['map_x', 'map_y']);
        });
    }
};
