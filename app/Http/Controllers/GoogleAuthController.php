<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\GoogleSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * Masuk / membuat akun memakai akun Google.
 *
 * Alur (OAuth 2.0 Authorization Code):
 *  1. Siswa menekan tombol "Masuk dengan Google" -> diarahkan ke /auth/google
 *  2. Aplikasi mengalihkan ke halaman izin milik Google
 *  3. Setelah siswa menyetujui, Google mengembalikannya ke /auth/google/callback
 *     dengan membawa kode sekali pakai
 *  4. Aplikasi menukar kode itu menjadi data profil (nama, email, foto, id)
 *  5. Akun dicocokkan berdasarkan google_id -> lalu email -> kalau belum ada, dibuatkan baru
 */
class GoogleAuthController extends Controller
{
    /** Mengalihkan siswa ke halaman izin Google. */
    public function redirect(Request $request): RedirectResponse
    {
        if (!self::configured()) {
            return redirect('/?google=belum-diatur');
        }

        // Google hanya mengizinkan satu alamat balasan tetap (GOOGLE_REDIRECT_URI), yaitu
        // domain publik. Bila siswa membuka aplikasi lewat alamat lain (mis. IP lokal
        // 10.10.10.100:24109), cookie sesinya tersimpan pada host yang berbeda sehingga
        // penanda "state" tidak terbaca saat Google mengembalikannya -> login selalu gagal.
        // Karena itu siswa dipindahkan dulu ke host yang sama dengan alamat balasan.
        if ($tujuan = self::hostPengalihan($request)) {
            return redirect()->away($tujuan);
        }

        $cred = self::credentials();
        if (!$cred) {
            return redirect('/?google=belum-diatur');
        }

        try {
            return Socialite::driver('google')
                ->with(['client_id' => $cred['client_id'], 'client_secret' => $cred['client_secret'], 'redirect' => $cred['redirect']])
                ->scopes(['openid', 'profile', 'email'])
                ->redirect();
        } catch (Throwable $e) {
            report($e);

            return redirect('/?google=gagal');
        }
    }

    /** Menerima balasan dari Google, lalu memasukkan siswa ke aplikasi. */
    public function callback(Request $request): RedirectResponse
    {
        if (!self::configured()) {
            return redirect('/?google=belum-diatur');
        }

        // Siswa menekan "Batal" di halaman izin Google.
        if ($request->filled('error')) {
            return redirect('/?google=dibatalkan');
        }

        $cred = self::credentials();
        if (!$cred) {
            return redirect('/?google=belum-diatur');
        }

        try {
            $google = Socialite::driver('google')
                ->with(['client_id' => $cred['client_id'], 'client_secret' => $cred['client_secret'], 'redirect' => $cred['redirect']])
                ->user();
        } catch (InvalidStateException $e) {
            // Penanda "state" tidak cocok: biasanya karena sesi hilang (cookie diblokir,
            // alamat yang dipakai berbeda dengan alamat balasan, atau halaman izin
            // dibiarkan terlalu lama lalu kedaluwarsa).
            report($e);

            return redirect('/?google=sesi-hilang');
        } catch (Throwable $e) {
            report($e);

            return redirect('/?google=gagal');
        }

        $googleId = (string) $google->getId();
        $email = strtolower(trim((string) $google->getEmail()));
        if ($googleId === '' || $email === '') {
            return redirect('/?google=gagal');
        }

        // 1) Akun yang sudah pernah masuk lewat Google.
        $user = User::where('google_id', $googleId)->first();

        // 2) Akun lama yang emailnya sama: ditautkan agar siswa tidak punya dua akun.
        if (!$user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->forceFill(['google_id' => $googleId])->save();
            }
        }

        // 3) Belum punya akun sama sekali: dibuatkan akun baru.
        if (!$user) {
            $user = User::create([
                'name' => self::cleanName($google->getName() ?: Str::before($email, '@')),
                'username' => self::uniqueUsername($email),
                'email' => $email,
                // Kolom password wajib terisi. Diisi acak karena siswa masuk lewat Google.
                // must_set_password menandai agar siswa boleh membuat kata sandi pertama
                // tanpa dimintai kata sandi lama (lihat ProfileController@password).
                'password' => Str::random(32),
                'google_id' => $googleId,
                'must_set_password' => true,
            ]);
        }

        // Foto Google dipakai hanya bila siswa belum mengunggah fotonya sendiri.
        $avatar = $google->getAvatar();
        if ($avatar && !$user->photo) {
            $user->forceFill(['google_avatar' => $avatar])->save();
        }

        // Akun yang diblokir admin tetap tidak boleh masuk, walau lewat Google.
        if ($user->is_blocked) {
            return redirect('/?google=diblokir');
        }

        // Kata sandi sementara yang mungkin masih tersimpan dibatalkan.
        $user->forceFill(['temp_password' => null, 'temp_password_expires_at' => null])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect('/?google=berhasil');
    }

    /** Benar bila Client ID, Secret, dan redirect URI sudah diisi (.env atau admin panel). */
    public static function configured(): bool
    {
        $c = config('services.google');
        if (!empty($c['client_id']) && !empty($c['client_secret']) && !empty($c['redirect'])) {
            return true;
        }
        // Fallback ke pengaturan admin (database)
        $s = GoogleSettings::get();
        return $s !== null;
    }

    /** Ambil kredensial Google: .env diprioritaskan, lalu admin panel. */
    private static function credentials(): ?array
    {
        $c = config('services.google');
        if (!empty($c['client_id']) && !empty($c['client_secret'])) {
            return $c;
        }
        return GoogleSettings::get();
    }

    /**
     * Mengembalikan alamat tujuan bila siswa perlu dipindahkan ke host yang sama
     * dengan GOOGLE_REDIRECT_URI, atau null bila host-nya sudah benar.
     *
     * Ini mencegah kegagalan "state tidak cocok": cookie sesi terikat pada satu host,
     * jadi perjalanan harus dimulai dan diakhiri pada host yang sama.
     */
    private static function hostPengalihan(Request $request): ?string
    {
        $redirect = (string) config('services.google.redirect');
        $bagian = parse_url($redirect);
        if (empty($bagian['host'])) {
            return null;
        }

        // Host yang diharapkan Google, lengkap dengan porta bila bukan porta bawaan.
        $skema = $bagian['scheme'] ?? 'https';
        $hostTujuan = strtolower($bagian['host']);
        $portaTujuan = $bagian['port'] ?? null;

        if (strtolower($request->getHost()) === $hostTujuan) {
            return null;
        }

        $alamat = $skema.'://'.$hostTujuan;
        if ($portaTujuan && !in_array((int) $portaTujuan, [80, 443], true)) {
            $alamat .= ':'.$portaTujuan;
        }

        return $alamat.'/auth/google';
    }

    /** Merapikan nama dari Google: spasi ganda dibuang, dipotong maksimal 40 karakter. */
    private static function cleanName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name));

        return Str::limit($name !== '' ? $name : 'Siswa', 40, '');
    }

    /**
     * Membuat username dari bagian depan email, lalu menambah angka bila sudah dipakai.
     * Mengikuti aturan pendaftaran biasa: 3-20 karakter huruf/angka/titik/garis bawah/strip.
     */
    private static function uniqueUsername(string $email): string
    {
        $base = strtolower(preg_replace('/[^A-Za-z0-9._-]/', '', Str::before($email, '@')));
        $base = trim($base, '._-');
        if (strlen($base) < 3) {
            $base = 'siswa'.$base;
        }
        $base = substr($base, 0, 16);

        $candidate = $base;
        $i = 1;
        while ($candidate === 'admin' || User::where('username', $candidate)->exists()) {
            $suffix = (string) (++$i);
            $candidate = substr($base, 0, 20 - strlen($suffix)).$suffix;
        }

        return $candidate;
    }
}
