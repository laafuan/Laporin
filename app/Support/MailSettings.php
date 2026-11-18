<?php

namespace App\Support;

use App\Models\Setting;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * Email pengirim yang diisi admin dari menu Profil (mis. akun Gmail sekolah + Sandi Aplikasi).
 * Dipakai untuk mengirim kata sandi sementara ke siswa (lupa kata sandi dan reset oleh admin).
 * Sandi disimpan terenkripsi dengan APP_KEY dan tidak pernah dikirim kembali ke tampilan.
 */
final class MailSettings
{
    public const KEY = 'mail';

    /** Pengganti pemeriksaan login SMTP (dipakai tes agar tidak menghubungi Gmail sungguhan). */
    public static ?Closure $verifyUsing = null;

    /** @return array{host:string,port:int,username:string,password:string,from_name:string}|null */
    public static function get(): ?array
    {
        $s = Setting::read(self::KEY);
        if (!$s || empty($s['username']) || empty($s['password'])) {
            return null;
        }
        try {
            $s['password'] = Crypt::decryptString($s['password']);
        } catch (Throwable) {
            return null; // APP_KEY berubah: sandi lama tidak bisa dibaca, admin perlu mengisi ulang
        }

        return $s + ['host' => 'smtp.gmail.com', 'port' => 587, 'from_name' => 'Laporin'];
    }

    /** Sandi tersimpan (sudah dibuka), untuk memeriksa ulang saat admin menyimpan tanpa mengganti sandi. */
    public static function storedPassword(): ?string
    {
        return self::get()['password'] ?? null;
    }

    public static function save(array $data, ?string $newPassword): void
    {
        $old = Setting::read(self::KEY) ?? [];
        Setting::write(self::KEY, [
            'host' => $data['host'],
            'port' => (int) $data['port'],
            'username' => $data['username'],
            'from_name' => $data['from_name'] ?: 'Laporin',
            'password' => $newPassword !== null && $newPassword !== '' ? Crypt::encryptString($newPassword) : ($old['password'] ?? null),
            'verified_at' => now()->toIso8601String(),
            'last_error' => null,
            'last_error_at' => null,
        ]);
    }

    /** Untuk tampilan admin: tanpa sandi. status: off | ok | error | unverified. */
    public static function summary(): array
    {
        $s = Setting::read(self::KEY) ?? [];
        $active = self::get() !== null;

        return [
            'host' => $s['host'] ?? 'smtp.gmail.com',
            'port' => (int) ($s['port'] ?? 587),
            'username' => $s['username'] ?? '',
            'from_name' => $s['from_name'] ?? 'Laporin',
            'hasPassword' => !empty($s['password']),
            'active' => $active,
            'status' => !$active ? 'off' : (!empty($s['last_error']) ? 'error' : (!empty($s['verified_at']) ? 'ok' : 'unverified')),
            'verifiedAt' => $s['verified_at'] ?? null,
            'lastError' => $s['last_error'] ?? null,
            'lastErrorAt' => $s['last_error_at'] ?? null,
            'lastSentAt' => $s['last_sent_at'] ?? null,
        ];
    }

    /** Memakai pengaturan dari aplikasi untuk pengiriman email di permintaan ini. */
    public static function apply(): bool
    {
        $s = self::get();
        if (!$s) {
            return false;
        }
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $s['host'],
            'mail.mailers.smtp.port' => $s['port'],
            'mail.mailers.smtp.scheme' => $s['port'] === 465 ? 'smtps' : null,
            'mail.mailers.smtp.username' => $s['username'],
            'mail.mailers.smtp.password' => $s['password'],
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => $s['username'],
            'mail.from.name' => $s['from_name'],
        ]);
        Mail::purge('smtp');

        return true;
    }

    /**
     * Mencoba masuk ke server SMTP dengan email dan sandi ini tanpa mengirim email.
     * Mengembalikan null bila berhasil, atau penjelasan masalahnya untuk admin.
     */
    public static function verify(string $host, int $port, string $username, string $password): ?string
    {
        if (self::$verifyUsing) {
            return (self::$verifyUsing)($host, $port, $username, $password);
        }
        try {
            $transport = new EsmtpTransport($host, $port, $port === 465);
            $transport->getStream()->setTimeout(15);
            $transport->setUsername($username);
            $transport->setPassword($password);
            $transport->start();   // EHLO, STARTTLS, lalu AUTH: gagal di sini bila email/sandi ditolak
            $transport->stop();
        } catch (Throwable $e) {
            return self::explain($e);
        }

        return null;
    }

    /** Menerjemahkan pesan error SMTP yang umum menjadi penjelasan yang bisa ditindaklanjuti admin. */
    public static function explain(Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '534') || stripos($msg, 'Application-specific password') !== false) {
            return 'Gmail meminta Sandi Aplikasi. Aktifkan Verifikasi 2 Langkah di akun Google, buat Sandi Aplikasi, lalu isi sandi 16 huruf itu di sini (bukan sandi Gmail biasa).';
        }
        if (str_contains($msg, '535') || stripos($msg, 'authenticate') !== false || stripos($msg, 'Username and Password not accepted') !== false) {
            return 'Gmail menolak login: alamat email atau Sandi Aplikasi salah. Pastikan akun Gmail ini benar-benar milik sekolah (bukan contoh seperti test@gmail.com), Verifikasi 2 Langkah sudah aktif, dan yang diisi adalah Sandi Aplikasi 16 huruf, bukan sandi Gmail biasa.';
        }
        if (stripos($msg, 'Connection could not be established') !== false || stripos($msg, 'timed out') !== false || stripos($msg, 'getaddrinfo') !== false) {
            return 'Server tidak bisa terhubung ke server email ('.mb_strimwidth($msg, 0, 120, '…').'). Periksa alamat server SMTP dan port (Gmail: smtp.gmail.com, port 587).';
        }
        if (str_contains($msg, '550') || str_contains($msg, '553')) {
            return 'Server email menolak alamat tujuan atau pengirim: '.mb_strimwidth($msg, 0, 150, '…');
        }

        return 'Pengiriman email gagal: '.mb_strimwidth($msg, 0, 180, '…');
    }

    /** Mencatat hasil pengiriman terakhir agar admin tahu bila email pengirim bermasalah. */
    public static function recordResult(?string $error): void
    {
        $s = Setting::read(self::KEY);
        if (!$s) {
            return;
        }
        if ($error === null) {
            $s['last_sent_at'] = now()->toIso8601String();
            $s['last_error'] = null;
            $s['last_error_at'] = null;
        } else {
            $s['last_error'] = $error;
            $s['last_error_at'] = now()->toIso8601String();
        }
        Setting::write(self::KEY, $s);
    }
}
