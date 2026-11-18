<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('category', 40);
            $table->string('location', 100);
            $table->text('description');
            $table->longText('photo')->nullable();
            $table->boolean('has_photo')->default(false); // agar daftar laporan tidak perlu memuat foto
            $table->string('status', 20)->default('Menunggu')->index(); // Menunggu, Diproses, Selesai, Ditolak
            $table->text('tanggapan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
