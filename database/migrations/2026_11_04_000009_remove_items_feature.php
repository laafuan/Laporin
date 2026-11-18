<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fitur barang + QR code dihapus (kode barang belum ditentukan sekolah): kolom reports.item_id dan tabel items dibuang.
// Saat dihapus, server tidak menyimpan satu pun data barang. Bila fitur ini dipakai lagi, jalankan rollback migrasi ini.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reports', 'item_id')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropConstrainedForeignId('item_id');
            });
        }
        Schema::dropIfExists('items');
    }

    public function down(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('category', 40);
            $table->string('location', 100);
            $table->string('merk', 60)->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('kondisi', 20)->default('Baik');
            $table->text('keterangan')->nullable();
            $table->longText('photo')->nullable();
            $table->boolean('has_photo')->default(false);
            $table->timestamps();
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
