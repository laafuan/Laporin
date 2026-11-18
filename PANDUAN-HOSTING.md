# Panduan Memasang Laporin di Hosting

Dengan hosting, Laporin bisa dibuka dari mana saja selama 24 jam, dengan alamat tetap (mis. `https://laporin-sekolahku.com`).

File yang dipakai:
- **`laporin-hosting.zip`**: paket siap pakai. Sudah berisi `vendor` (tidak perlu Composer), file `.env` produksi,
  dan database SQLite yang sudah berisi semua tabel dan akun admin.
- **`AKUN-ADMIN-HOSTING.txt`**: username dan kata sandi admin untuk paket ini. **Jangan diunggah** ke hosting.

## 1. Pilih hosting
Cari hosting (biasanya disebut *shared hosting* atau *hosting cPanel*) yang memenuhi syarat ini:
- PHP **8.2 atau lebih baru**
- Ekstensi PHP: `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `gd`, `ctype`, `tokenizer`, `xml`
- Server web Apache atau LiteSpeed (mendukung file `.htaccess`)
- SSL gratis (Let's Encrypt / AutoSSL) agar alamatnya `https://`

Sebagian besar hosting cPanel di Indonesia memenuhi syarat ini. Pendaftaran akun dan pembayaran hosting kamu lakukan sendiri.

## 2. Atur versi PHP
Di cPanel buka **Select PHP Version** (atau **MultiPHP Manager**), pilih **8.2** atau lebih baru,
lalu pastikan ekstensi di atas dicentang.

## 3. Unggah dan ekstrak
1. Buka **File Manager** di cPanel.
2. Pilih salah satu cara:
   - **Cara A, disarankan (subdomain/domain tambahan):** buat folder, mis. `laporin`, di luar `public_html`.
     Unggah `laporin-hosting.zip` ke sana lalu **Extract**. Saat membuat subdomain/domain di cPanel,
     arahkan *Document Root*-nya ke `laporin/public`.
   - **Cara B (domain utama):** unggah `laporin-hosting.zip` langsung ke `public_html` lalu **Extract**.
     File `.htaccess` yang ikut di dalam paket otomatis mengarahkan pengunjung ke folder `public`.
3. Hapus file zip setelah diekstrak.

> File Manager kadang menyembunyikan file berawalan titik. Aktifkan **Show Hidden Files (dotfiles)**
> lewat tombol *Settings* agar `.env` dan `.htaccess` terlihat.

## 4. Ubah `.env`
Klik kanan `.env` lalu pilih **Edit**, kemudian ubah:
```
APP_URL=https://alamat-hosting-kamu.com
SCHOOL_NAME="Nama Sekolahmu"
SCHOOL_CITY=Kotamu
```
Bagian lainnya tidak perlu diubah.

## 5. Izin folder
Folder `storage`, `bootstrap/cache`, dan `database` harus bisa ditulisi server.
Di File Manager, pilih folder lalu **Permissions**: folder `755`, file `database/database.sqlite` `644`.
Kalau muncul error saat menyimpan data, coba `775` dan `664`.

## 6. Aktifkan SSL
Di cPanel buka **SSL/TLS Status** atau **Let's Encrypt** lalu jalankan untuk domainmu.

## 7. Coba dan periksa keamanan
1. Buka `https://alamat-hosting-kamu.com`, lalu masuk dengan akun di `AKUN-ADMIN-HOSTING.txt`.
2. **Wajib dicek** (apalagi kalau memakai Cara B), buka alamat berikut. Keduanya **tidak boleh** menampilkan isi file
   atau mengunduh apa pun; yang benar adalah halaman "404" atau "403":
   - `https://alamat-hosting-kamu.com/.env`
   - `https://alamat-hosting-kamu.com/database/database.sqlite`

   Kalau salah satunya terbuka, segera hapus file tersebut dari hosting, lalu pakai Cara A
   atau hubungi bantuan hosting (biasanya karena server tidak membaca `.htaccess`).
3. Ganti kata sandi admin lewat menu **Profil**.

## Memakai MySQL (opsional)
SQLite sudah cukup untuk satu sekolah. Kalau ingin MySQL:
1. Buat database dan user di **MySQL Databases** cPanel.
2. Di `.env`, ganti `DB_CONNECTION=sqlite` dengan:
   ```
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=nama_database
   DB_USERNAME=nama_user
   DB_PASSWORD=kata_sandi_database
   ```
3. Buka **Terminal** di cPanel, masuk ke folder Laporin, lalu jalankan `php artisan migrate --seed --force`.
   Akun admin dari perintah ini memakai kata sandi bawaan (`admin` / `Admin1234`), jadi **langsung ganti** lewat menu Profil.
   Kalau tidak ada Terminal, buat **Cron Job** sekali jalan dengan perintah
   `cd ~/laporin && php artisan migrate --seed --force` (sesuaikan foldernya), lalu hapus cron job itu setelah selesai.

## Memperbarui aplikasi nanti
Unggah file yang berubah saja. **Jangan menimpa** `.env` dan `database/database.sqlite`,
karena keduanya berisi pengaturan dan semua data laporan.
Kalau ada migrasi baru, jalankan `php artisan migrate --force` lewat Terminal.

## Kalau muncul error
- **Error 500 / halaman putih:** lihat `storage/logs/laravel.log`. Penyebab tersering: versi PHP di bawah 8.2,
  ekstensi belum aktif, atau izin folder `storage`/`database`.
- **Tampilan tanpa warna (CSS tidak termuat):** pastikan `APP_URL` di `.env` sudah benar dan memakai `https://`.
