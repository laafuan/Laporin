<?php

namespace App\Support;

use App\Models\Setting;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Pengaturan "Masuk dengan Google" yang diisi admin dari menu admin.
 * Client ID & Client Secret disimpan terenkripsi, tidak pernah dikirim ke tampilan.
 * Redirect URI sudah tetap ke domain publik, tidak perlu diisi ulang.
 */
final class GoogleSettings
{
    public const KEY = 'google';

    public static ?Closure $verifyUsing = null;

    /** @return array{client_id:string,client_secret:string,redirect:string}|null */
    public static function get(): ?array
    {
        $s = Setting::read(self::KEY);
        if (!$s || empty($s['client_id']) || empty($s['client_secret'])) {
            return null;
        }
        try {
            $s['client_secret'] = Crypt::decryptString($s['client_secret']);
        } catch (Throwable) {
            return null; // APP_KEY berubah
        }

        return $s + ['redirect' => config('services.google.redirect')];
    }

    public static function storedSecret(): ?string
    {
        return self::get()['client_secret'] ?? null;
    }

    public static function save(array $data, ?string $newSecret): void
    {
        $old = Setting::read(self::KEY) ?? [];
        Setting::write(self::KEY, [
            'client_id' => trim($data['client_id']),
            'client_secret' => $newSecret !== null && $newSecret !== '' ? Crypt::encryptString($newSecret) : ($old['client_secret'] ?? null),
            'verified_at' => now()->toIso8601String(),
            'last_error' => null,
            'last_error_at' => null,
        ]);
    }

    /** Untuk tampilan admin. status: off | ok | error | unverified */
    public static function summary(): array
    {
        $s = Setting::read(self::KEY) ?? [];
        $active = self::get() !== null;

        return [
            'client_id' => $s['client_id'] ?? '',
            'hasSecret' => !empty($s['client_secret']),
            'active' => $active,
            'status' => !$active ? 'off' : (!empty($s['last_error']) ? 'error' : (!empty($s['verified_at']) ? 'ok' : 'unverified')),
            'verifiedAt' => $s['verified_at'] ?? null,
            'lastError' => $s['last_error'] ?? null,
            'lastErrorAt' => $s['last_error_at'] ?? null,
        ];
    }

    /** Mencoba memvalidasi kredensial dengan meminta token ke Google (tanpa menyimpan). */
    public static function verify(string $clientId, string $clientSecret, string $redirectUri): ?string
    {
        if (self::$verifyUsing) {
            return (self::$verifyUsing)($clientId, $clientSecret, $redirectUri);
        }
        try {
            // Gunakan HTTP client bawaan Laravel (Guzzle) untuk cek ke Google OAuth2 endpoint.
            // Kalau client_id/secret salah, Google mengembalikan 400 invalid_client.
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $resp = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => 'dummy-code-hanya-untuk-cek-kredensial', // Google akan tolak tapi *setelah* memvalidasi client_id/secret
                    'redirect_uri' => $redirectUri,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
                'http_errors' => false,
            ]);
            $body = json_decode($resp->getBody()->getContents(), true);
            // Kita harap error "invalid_grant" (kode tidak valid) = kredensial OK.
            // Error "invalid_client" = kredensial salah.
            $err = $body['error'] ?? '';
            if ($err === 'invalid_client') {
                return 'Client ID atau Client Secret tidak valid. Pastikan Anda menyalin dari Google Cloud Console → Credentials → OAuth 2.0 Client IDs (tipe Web application).';
            }
            if ($err === 'invalid_grant' || $err === 'unsupported_grant_type') {
                return null; // kredensial diterima
            }
            return 'Tidak bisa memvalidasi: ' . ($body['error_description'] ?? $err);
        } catch (Throwable $e) {
            return 'Gagal menghubungi server Google OAuth: ' . $e->getMessage();
        }
    }

    public static function recordResult(?string $error): void
    {
        $s = Setting::read(self::KEY);
        if (!$s) return;
        if ($error === null) {
            $s['last_error'] = null;
            $s['last_error_at'] = null;
        } else {
            $s['last_error'] = $error;
            $s['last_error_at'] = now()->toIso8601String();
        }
        Setting::write(self::KEY, $s);
    }
}