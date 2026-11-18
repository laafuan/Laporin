<?php

namespace App\Support;

use Illuminate\Http\Response;

/** Memeriksa dan menormalkan foto kiriman pengguna (data URL base64). */
final class Photo
{
    public static function clean(?string $value, int $maxChars): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (strlen($value) > $maxChars) {
            abort(422, 'Ukuran foto terlalu besar.');
        }
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $value, $m)) {
            abort(422, 'Format foto tidak valid.');
        }
        $binary = base64_decode(substr($value, strlen($m[0])), true);
        if ($binary === false) {
            abort(422, 'Format foto tidak valid.');
        }
        $info = @getimagesizefromstring($binary);
        if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            abort(422, 'File bukan gambar yang valid.');
        }
        // Dikodekan ulang sehingga hanya berisi karakter base64 yang aman.
        return 'data:' . $info['mime'] . ';base64,' . base64_encode($binary);
    }

    /** Menyajikan foto tersimpan (data URL) sebagai gambar biasa. */
    public static function response(?string $dataUrl, string $cache = 'private, max-age=86400'): Response
    {
        abort_if(empty($dataUrl), 404);

        [$meta, $base64] = array_pad(explode(',', $dataUrl, 2), 2, '');
        abort_unless(preg_match('#^data:(image/(?:jpeg|png|webp));base64$#', $meta, $m), 404);

        return response(base64_decode($base64), 200, [
            'Content-Type' => $m[1],
            'Cache-Control' => $cache,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
