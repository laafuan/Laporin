# Laporin — Siswa Melapor & Beraspirasi

Aplikasi web pelaporan kerusakan sarana prasarana sekolah. Siswa melapor dengan
foto dan titik lokasi di denah sekolah; admin menindaklanjuti sampai selesai.

Dibangun dengan Laravel 11 (PHP 8.3), JavaScript vanilla (tanpa build step), dan SQLite.


## Cara menjalankan

Butuh PHP 8.2+ dengan ekstensi `sqlite3`, `mbstring`, `openssl`, `fileinfo`,
serta Composer.

```bash
# 1. Pasang dependensi
composer install

# 2. Siapkan konfigurasi
cp .env.example .env
php artisan key:generate

# 3. Siapkan database (SQLite)
touch database/database.sqlite
php artisan migrate --seed

# 4. Siapkan folder foto
php artisan storage:link

# 5. Jalankan
php artisan serve
```

Buka http://127.0.0.1:8000

Akun admin bawaan dari seeder ada di `database/seeders/DatabaseSeeder.php`.
**Ganti kata sandinya sebelum dipakai sungguhan.**


## Fitur

**Untuk siswa**
- Lapor kerusakan dengan foto wajib sebagai bukti
- Tandai titik kerusakan di denah sekolah
- Pantau status laporan sendiri beserta catatan dari admin
- Nota laporan siap cetak
- Lupa kata sandi lewat email
- Masuk/daftar dengan akun Google

**Untuk admin**
- Tentukan tingkat kerusakan (Ringan/Sedang/Berat/Darurat)
- Perbarui status: Diproses, Selesai, Ditolak — dengan catatan untuk pelapor
- Ekspor laporan ke Excel dengan filter tanggal
- Kelola dan blokir akun siswa
- Atur pengiriman email dan kredensial Google dari menu Profil

**Publik**
- Beranda menampilkan semua laporan tanpa perlu akun
- Memperbarui diri otomatis tiap 10 detik


## Mengaktifkan "Masuk dengan Google"

Fitur ini opsional. Tanpa kredensial, tombolnya tetap aman — muncul pesan
"belum diaktifkan oleh admin", bukan error.

1. Buka https://console.cloud.google.com/ → buat project baru
2. **APIs & Services → OAuth consent screen** → pilih **External** → isi nama
   aplikasi dan email → simpan
3. Di **Test users**, tambahkan email yang akan dipakai menguji
4. **Credentials → Create credentials → OAuth client ID** → pilih tipe
   **Web application** (bukan Desktop app)
   - *Authorized JavaScript origins*: alamat aplikasi, mis.
     `https://laporin.example.com`
   - *Authorized redirect URIs*: `https://laporin.example.com/auth/google/callback`
     — harus **sama persis**, tanpa garis miring di akhir
5. Salin **Client ID** dan **Client Secret**
6. Masuk sebagai admin → **Profil** → bagian **Masuk dengan Google** → tempel
   keduanya → **Simpan**

Aplikasi langsung memverifikasi kredensial ke Google. Kalau statusnya menjadi
**Aktif**, tombol di halaman masuk sudah bisa dipakai.

Kredensial disimpan terenkripsi di database. Bisa juga diisi lewat
`GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` di `.env` — kalau keduanya ada,
yang dari menu admin dipakai lebih dulu.

**Catatan:** `APP_URL` di `.env` harus sama dengan alamat yang dipakai siswa.
Kalau beda (mis. `APP_URL` berisi alamat IP tapi siswa membuka lewat domain),
sesi OAuth akan hilang di tengah jalan.


## Mengaktifkan email

Bawaan `MAIL_MAILER=log` hanya mencatat email ke `storage/logs/laravel.log`.

Untuk mengirim sungguhan, atur dari menu admin (**Profil → Pengiriman email**)
atau lewat `.env`. Khusus Gmail, `MAIL_PASSWORD` harus
[Sandi Aplikasi](https://myaccount.google.com/apppasswords) 16 huruf, bukan
sandi Gmail biasa.


## Struktur

```
app/Http/Controllers/   AuthController, ReportController, AdminReportController,
                        AdminUserController, ProfileController,
                        GoogleAuthController, GoogleSettingsController,
                        SettingsController
app/Support/            GoogleSettings, MailSettings — konfigurasi runtime
                        yang disimpan terenkripsi di database
app/Models/             User, Report, Photo, Setting
resources/views/        app.blade.php — satu halaman, sisanya digerakkan JS
public/js/script.js     seluruh logika antarmuka
public/css/style.css    gaya tampilan
database/migrations/    skema database
routes/web.php          rute halaman dan API
```


## Catatan teknis

- Autentikasi berbasis sesi (bukan token), CSRF aktif di semua permintaan
- Foto laporan disimpan di `storage/app/public/photos`
- Akun hasil pendaftaran Google diberi kata sandi acak dan ditandai
  `must_set_password`, agar bisa membuat kata sandi pertama tanpa diminta
  kata sandi lama
- Pemeriksaan `is_blocked` berlaku di kedua jalur masuk (kata sandi dan Google)
- URL foto profil Google hanya diterima dari domain `googleusercontent.com`
- Pakai SQLite dengan satu worker. Beberapa worker sekaligus
  (`PHP_CLI_SERVER_WORKERS`) berisiko `database is locked`


## Lisensi

Proyek tugas akhir sekolah. Bebas dipakai dan dimodifikasi.
