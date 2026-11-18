<?php

namespace App\Support;

/** Alamat file CSS/JS dengan nomor versi, agar browser selalu memuat versi terbaru setelah aplikasi diperbarui. */
final class Asset
{
    public static function url(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
