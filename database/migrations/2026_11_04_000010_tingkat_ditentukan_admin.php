<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tingkat kerusakan kini ditentukan admin setelah mengecek laporan, bukan dipilih siswa.
 * Kolomnya boleh kosong ("belum dinilai") sampai admin menyetujui laporan.
 * Laporan yang masih Menunggu dikosongkan tingkatnya agar dinilai ulang oleh admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('tingkat', 10)->nullable()->default(null)->change();
        });
        DB::table('reports')->where('status', 'Menunggu')->update(['tingkat' => null]);
    }

    public function down(): void
    {
        DB::table('reports')->whereNull('tingkat')->update(['tingkat' => 'Ringan']);
        Schema::table('reports', function (Blueprint $table) {
            $table->string('tingkat', 10)->default('Ringan')->change();
        });
    }
};
