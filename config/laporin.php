<?php

// Identitas sekolah yang tampil di halaman masuk dan nota persetujuan. Ubah lewat file .env.
return [
    'school' => env('SCHOOL_NAME', 'SMKN 5 Surakarta'),
    'city' => env('SCHOOL_CITY', 'Surakarta'),

    // Zona waktu sekolah: dipakai untuk filter tanggal dan tanggal di file Excel.
    'timezone' => env('SCHOOL_TIMEZONE', 'Asia/Jakarta'),

    // Masa berlaku kata sandi sementara yang dikirim ke email saat lupa kata sandi (menit).
    // Pengaturan server email ada di .env (MAIL_MAILER, MAIL_HOST, dst.).
    'temp_password_minutes' => 30,
];
