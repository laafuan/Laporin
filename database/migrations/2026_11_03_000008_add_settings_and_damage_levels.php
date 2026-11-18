<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1. Tabel pengaturan aplikasi (mis. email pengirim untuk lupa kata sandi), diisi admin dari aplikasi.
// 2. Tingkat kerusakan kini 4 level (Ringan, Sedang, Berat, Darurat): laporan lama "Biasa" menjadi "Ringan".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('reports')->where('tingkat', 'Biasa')->update(['tingkat' => 'Ringan']);
    }

    public function down(): void
    {
        DB::table('reports')->where('tingkat', 'Ringan')->update(['tingkat' => 'Biasa']);
        Schema::dropIfExists('settings');
    }
};
