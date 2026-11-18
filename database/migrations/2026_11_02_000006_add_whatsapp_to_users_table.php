<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Data untuk fitur lupa kata sandi. Kolom phone kemudian diganti email (migrasi 000007).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable();                 // format 628xxxxxxxxxx
            $table->string('temp_password')->nullable();             // kata sandi sementara (ter-hash)
            $table->timestamp('temp_password_expires_at')->nullable();
            $table->timestamp('reset_requested_at')->nullable();     // permintaan lupa sandi yang menunggu admin
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'temp_password', 'temp_password_expires_at', 'reset_requested_at']);
        });
    }
};
