<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Di hosting ber-HTTPS, semua alamat yang dibuat Laravel (CSS, JS, gambar) ikut https.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Satu kelas biasanya keluar internet lewat IP yang sama, jadi batas percobaan dihitung
        // per username + IP. Batas per IP dibuat longgar agar satu kelas bisa masuk bersamaan.
        $tooMany = fn () => response()->json(['message' => 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.'], 429);

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by(strtolower((string) $request->input('username')).'|'.$request->ip())->response($tooMany),
            Limit::perMinute(200)->by('ip|'.$request->ip())->response($tooMany),
        ]);
    }
}
