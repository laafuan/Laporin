<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Data barang sekolah. Setiap barang punya kode unik yang dicetak sebagai QR code.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                 // isi QR, mis. BRG-7K2M9QXA
            $table->string('name', 100);
            $table->string('category', 40);
            $table->string('location', 100);
            $table->string('merk', 60)->nullable();               // merk / tipe
            $table->unsignedSmallInteger('tahun')->nullable();     // tahun pembuatan / pengadaan
            $table->string('kondisi', 20)->default('Baik');       // Baik, Rusak Ringan, Rusak Berat
            $table->text('keterangan')->nullable();
            $table->longText('photo')->nullable();
            $table->boolean('has_photo')->default(false);
            $table->timestamps();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete(); // barang yang dilaporkan (opsional)
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
        });
        Schema::dropIfExists('items');
    }
};
