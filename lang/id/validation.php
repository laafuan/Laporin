<?php

// Pesan validasi bahasa Indonesia (APP_LOCALE=id). Pesan yang tidak ada di sini memakai bahasa Inggris.
return [
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'integer' => ':Attribute harus berupa angka bulat.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'confirmed' => 'Konfirmasi :attribute tidak sama.',
    'regex' => 'Format :attribute tidak valid.',
    'min' => [
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'max' => [
        'numeric' => ':Attribute maksimal :max.',
        'string' => ':Attribute maksimal :max karakter.',
    ],

    'attributes' => [
        'name' => 'nama',
        'username' => 'username',
        'password' => 'kata sandi',
        'current_password' => 'kata sandi lama',
        'title' => 'judul laporan',
        'category' => 'kategori',
        'location' => 'lokasi',
        'desc' => 'deskripsi',
        'jumlah' => 'jumlah barang',
        'tingkat' => 'tingkat kerusakan',
        'photo' => 'foto',
        'status' => 'status',
        'tanggapan' => 'catatan',
        'email' => 'email',
    ],
];
