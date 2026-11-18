<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Throwable;

/** Mengirim kata sandi sementara ke email pengguna. */
final class PasswordMail
{
    public const SUBJECT = 'Kata sandi sementara Laporin';

    /**
     * Pengiriman otomatis aktif bila email pengirim sudah diatur admin di aplikasi (menu Profil),
     * atau server email (SMTP) sudah diatur di .env.
     */
    public static function enabled(): bool
    {
        if (MailSettings::apply()) {
            return true;
        }
        $mailer = config('mail.default');
        if (in_array($mailer, ['log', 'array', null], true)) {
            return false;
        }

        return $mailer !== 'smtp' || filled(config('mail.mailers.smtp.host'));
    }

    /** Penjelasan kegagalan pengiriman terakhir (untuk ditampilkan ke admin), null bila tidak ada. */
    public static ?string $lastError = null;

    public static function send(string $email, string $body): bool
    {
        self::$lastError = null;
        if (!self::enabled()) {
            return false;
        }

        try {
            Mail::raw($body, fn ($m) => $m->to($email)->subject(self::SUBJECT));
            MailSettings::recordResult(null);

            return true;
        } catch (Throwable $e) {
            report($e);
            self::$lastError = MailSettings::explain($e);
            MailSettings::recordResult(self::$lastError);

            return false;
        }
    }

    /** "budi.santoso@gmail.com" menjadi "b•••o@gmail.com" agar siswa tahu email mana yang dituju. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $shown = mb_strlen($local) <= 2 ? mb_substr($local, 0, 1) : mb_substr($local, 0, 1).'•••'.mb_substr($local, -1);

        return $shown.'@'.$domain;
    }

    /** Tautan mailto: agar admin bisa mengirim email sendiri dari aplikasi emailnya. */
    public static function link(string $email, string $body): string
    {
        return 'mailto:'.$email.'?subject='.rawurlencode(self::SUBJECT).'&body='.rawurlencode($body);
    }

    public static function body(string $name, string $username, string $password, ?int $minutes): string
    {
        return "Halo {$name},\n\nini kata sandi sementara akun Laporin kamu.\n\n"
            ."Username   : {$username}\nKata sandi : {$password}\n\n"
            .($minutes ? "Kata sandi ini berlaku {$minutes} menit. " : '')
            ."Setelah masuk, segera ganti kata sandi di menu Profil.\n\n"
            .'Abaikan email ini bila kamu tidak memintanya; kata sandi lamamu tetap bisa dipakai.';
    }
}
