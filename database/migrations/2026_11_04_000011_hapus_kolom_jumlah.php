<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jumlah barang rusak tidak diisi lagi di formulir laporan, jadi kolomnya dihapus. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reports', 'jumlah')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropColumn('jumlah');
            });
        }
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah')->default(1);
        });
    }
};
