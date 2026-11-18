<?php

namespace App\Http\Controllers;

use App\Support\PasswordMail;
use App\Support\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /** Siswa dan admin boleh mengubah namanya sendiri. */
    public function name(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['name' => ['required', 'string', 'min:2', 'max:40']],
            ['name.required' => 'Nama wajib diisi.', 'name.min' => 'Nama minimal 2 karakter.', 'name.max' => 'Nama maksimal 40 karakter.']
        );
        $user = $request->user();
        $user->update(['name' => preg_replace('/\s+/', ' ', trim($data['name']))]);

        return response()->json(['user' => AuthController::userArray($user)]);
    }

    public function email(Request $request): JsonResponse
    {
        $data = $request->validate(
            ['email' => AuthController::emailRules()],
            ['email.required' => 'Isi alamat email.', 'email.email' => 'Alamat email tidak valid. Contoh: nama@gmail.com.']
        );
        $user = $request->user();
        $user->update(['email' => strtolower(trim($data['email']))]);

        return response()->json(['user' => AuthController::userArray($user)]);
    }

    /**
     * Ubah kata sandi. "Kata sandi lama" boleh diisi kata sandi sementara yang dikirim ke email
     * (fitur "Lupa kata sandi lama?"), selama belum kedaluwarsa.
     */
    public function password(Request $request): JsonResponse
    {
        $user = $request->user();

        // Akun yang dibuat lewat Google: kata sandinya diisi acak oleh sistem sehingga
        // pemiliknya tidak tahu. Untuk kata sandi pertama, "kata sandi lama" tidak diminta.
        $pertamaKaliGoogle = $user->must_set_password;

        $data = $request->validate([
            'current_password' => [$pertamaKaliGoogle ? 'nullable' : 'required', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ], [
            'current_password.required' => 'Semua kolom wajib diisi.',
            'password.required' => 'Semua kolom wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        if (!$pertamaKaliGoogle) {
            $tempOk = $user->temp_password && $user->temp_password_expires_at?->isFuture()
                && Hash::check($data['current_password'], $user->temp_password);
            if (!$tempOk && !Hash::check($data['current_password'], $user->password)) {
                return response()->json(['message' => 'Kata sandi lama salah. Pakai "Lupa kata sandi lama?" bila kamu lupa.'], 422);
            }
        }

        $user->forceFill([
            'password' => $data['password'],
            'must_set_password' => false,
            'temp_password' => null,
            'temp_password_expires_at' => null,
            'reset_requested_at' => null,
        ])->save();
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'user' => AuthController::userArray($user->fresh())]);
    }

    /** Pengguna yang sedang masuk lupa kata sandi lamanya: kirim kata sandi sementara ke emailnya. */
    public function forgotOld(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->email) {
            return response()->json(['message' => 'Isi email di profil dulu agar kata sandi sementara bisa dikirim.'], 422);
        }
        $sent = AuthController::sendTempPassword($user);
        $minutes = config('laporin.temp_password_minutes');

        return response()->json([
            'sent' => $sent,
            'message' => $sent
                ? "Kata sandi sementara dikirim ke {$user->email} (berlaku {$minutes} menit, periksa juga folder Spam). Isikan di kolom \"Kata sandi lama\", lalu buat kata sandi baru."
                : (PasswordMail::enabled()
                    ? 'Email gagal terkirim, jadi permintaanmu diteruskan ke admin untuk direset.'
                    : 'Email pengirim belum diatur admin, jadi permintaanmu diteruskan ke admin. Admin akan mengirim kata sandi baru ke email-mu.'),
        ]);
    }

    public function photo(Request $request): JsonResponse
    {
        $data = $request->validate(['photo' => ['required', 'string']]);
        $user = $request->user();
        $user->update(['photo' => Photo::clean($data['photo'], 150000)]);

        return response()->json(['user' => AuthController::userArray($user)]);
    }

    public function destroyPhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['photo' => null]);

        return response()->json(['user' => AuthController::userArray($user)]);
    }
}
