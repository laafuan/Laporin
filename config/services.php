<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Layanan Pihak Ketiga
    |--------------------------------------------------------------------------
    |
    | Pengaturan "Masuk dengan Google". Nilainya diambil dari file .env agar
    | kredensial rahasia tidak ikut tersimpan di dalam kode program.
    |
    | Cara mendapatkannya: Google Cloud Console > APIs & Services >
    | Credentials > Create OAuth client ID (tipe: Web application).
    | Authorized redirect URI harus sama persis dengan GOOGLE_REDIRECT_URI.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];
