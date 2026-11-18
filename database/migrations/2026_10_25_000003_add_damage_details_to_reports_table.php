<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah')->default(1);        // jumlah barang yang rusak
            $table->string('tingkat', 10)->default('Biasa');           // Biasa atau Berat
            $table->timestamp('approved_at')->nullable();              // waktu disetujui admin (untuk nota)
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['jumlah', 'tingkat', 'approved_at']);
        });
    }
};
