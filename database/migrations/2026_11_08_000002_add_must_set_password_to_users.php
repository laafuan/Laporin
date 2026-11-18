<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * must_set_password menandai akun yang dibuat lewat Google.
     * Akun seperti itu kata sandinya diisi acak oleh sistem, jadi pemiliknya tidak
     * mengetahuinya. Penanda ini membuat pemilik akun boleh membuat kata sandi
     * pertama tanpa dimintai kata sandi lama, supaya ia tetap bisa masuk manual
     * (mis. ketika tidak sedang memegang akun Google-nya).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'must_set_password')) {
                $table->boolean('must_set_password')->default(false)->after('google_avatar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'must_set_password')) {
                $table->dropColumn('must_set_password');
            }
        });
    }
};
