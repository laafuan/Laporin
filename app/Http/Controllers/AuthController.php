<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'username' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{3,20}$/'],
            'email' => self::emailRules(),
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ], [
            'name.required' => 'Semua kolom wajib diisi.',
            'name.min' => 'Nama minimal 2 karakter.',
            'name.max' => 'Nama maksimal 40 karakter.',
            'username.required' => 'Semua kolom wajib diisi.',
            'username.regex' => 'Username 3-20 karakter: huruf, angka, titik, garis bawah, atau strip (tanpa spasi).',
            'email.required' => 'Semua kolom wajib diisi.',
            'email.email' => 'Alamat email tidak valid. Contoh: nama@gmail.com.',
            'password.required' => 'Semua kolom wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        $username = strtolower($data['username']);
        if ($username === 'admin' || User::where('username', $username)->exists()) {
            return response()->json(['message' => 'Username sudah digunakan, coba username lain.'], 422);
        }

        User::create([
            'name' => preg_replace('/\s+/', ' ', trim($data['name'])),
            'username' => $username,
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username dan kata sandi wajib diisi.',
            'password.required' => 'Username dan kata sandi wajib diisi.',
        ]);

        $username = strtolower(trim($data['username']));
        $usedTemp = false;

        // Akun yang diblokir admin tidak boleh masuk, baik lewat kata sandi maupun Google.
        $calon = User::where('username', $username)->first();
        if ($calon && $calon->is_blocked) {
            return response()->json(['message' => 'Akun ini diblokir oleh admin. Hubungi admin sekolah.'], 403);
        }

        if (Auth::attempt(['username' => $username, 'password' => $data['password']])) {
            // Pemilik akun ingat kata sandinya: kata sandi sementara yang mungkin diminta orang lain dibatalkan.
            Auth::user()->forceFill(['temp_password' => null, 'temp_password_expires_at' => null])->save();
        } else {
            // Kata sandi sementara dari email. Selama belum dipakai, kata sandi lama tetap berlaku,
            // jadi orang lain yang iseng meminta reset tidak bisa mengunci akun pemiliknya.
            $user = User::where('username', $username)->first();
            $tempValid = $user && $user->temp_password && $user->temp_password_expires_at?->isFuture()
                && Hash::check($data['password'], $user->temp_password);
            if (!$tempValid) {
                return response()->json(['message' => 'Username atau kata sandi salah.'], 422);
            }
            $user->forceFill([
                'password' => $data['password'],
                'temp_password' => null,
                'temp_password_expires_at' => null,
                'reset_requested_at' => null,
            ])->save();
            Auth::login($user);
            $usedTemp = true;
        }

        $request->session()->regenerate();

        return response()->json(['user' => self::userArray(Auth::user()), 'mustChange' => $usedTemp]);
    }

    /**
     * Lupa kata sandi: siswa cukup mengisi username atau email. Kata sandi sementara langsung dikirim
     * ke email yang terdaftar di akunnya. Bila email belum diatur atau gagal terkirim, permintaan
     * diteruskan ke admin. Kata sandi lama tetap berlaku sampai kata sandi sementara dipakai.
     */
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
        ], [
            'username.required' => 'Isi username atau email akunmu.',
        ]);

        $login = strtolower(trim($data['username']));
        $user = User::where('is_admin', false)
            ->where(fn ($q) => $q->where('username', $login)->orWhere('email', $login))->first();
        if (!$user) {
            return response()->json(['message' => 'Username atau email itu tidak terdaftar. Periksa lagi ejaannya.'], 422);
        }
        if (!$user->email) {
            $user->forceFill(['reset_requested_at' => now()])->save();

            return response()->json(['mode' => 'admin', 'message' => 'Akun ini belum punya email, jadi kata sandi tidak bisa dikirim otomatis. Permintaanmu sudah diteruskan ke admin; minta kata sandi baru langsung ke admin.']);
        }

        $sent = self::sendTempPassword($user);
        $minutes = config('laporin.temp_password_minutes');

        return response()->json($sent
            ? ['mode' => 'email', 'message' => 'Kata sandi sementara sudah dikirim ke '.PasswordMail::mask($user->email)." (berlaku {$minutes} menit). Buka email itu, lalu masuk dengan kata sandi sementara. Periksa juga folder Spam."]
            : ['mode' => 'admin', 'message' => PasswordMail::enabled()
                ? 'Email gagal dikirim dari server sekolah. Permintaanmu sudah diteruskan ke admin untuk direset.'
                : 'Pengiriman email belum diatur oleh admin. Permintaanmu sudah diteruskan ke admin untuk direset.']);
    }

    /**
     * Mengirim kata sandi sementara ke email pengguna. Bila server email belum diatur atau pengiriman gagal,
     * permintaan dicatat agar admin bisa meresetnya. Mengembalikan true bila email terkirim.
     */
    public static function sendTempPassword(User $user): bool
    {
        $sent = false;
        if ($user->email && PasswordMail::enabled()) {
            $temp = User::randomPassword();
            $minutes = (int) config('laporin.temp_password_minutes');
            $sent = PasswordMail::send($user->email, PasswordMail::body($user->name, $user->username, $temp, $minutes));
            if ($sent) {
                $user->forceFill(['temp_password' => Hash::make($temp), 'temp_password_expires_at' => now()->addMinutes($minutes)])->save();
            }
        }
        if (!$sent) {
            $user->forceFill(['reset_requested_at' => now()])->save();
        }

        return $sent;
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'csrf' => csrf_token()]);
    }

    /** Aturan validasi email pengguna. */
    public static function emailRules(): array
    {
        return ['required', 'string', 'email:rfc', 'max:100'];
    }

    public static function userArray(User $user): array
    {
        return [
            'id' => $user->id,
            'user' => $user->username,
            'name' => $user->name,
            'photo' => $user->photo ?? '',
            // Foto dari Google berupa URL https, dipakai bila siswa belum mengunggah foto sendiri.
            'avatarUrl' => $user->photo ? '' : ($user->google_avatar ?? ''),
            'email' => $user->email ?? '',
            'admin' => (bool) $user->is_admin,
            // Dipakai tampilan: akun Google menyembunyikan "kata sandi lama" saat membuat sandi pertama.
            'google' => $user->usesGoogle(),
            'mustSetPassword' => (bool) $user->must_set_password,
        ];
    }
}
