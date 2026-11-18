<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    /** Admin mengganti kata sandi siswa yang lupa dengan kata sandi sementara, lalu mengirimnya ke email siswa. */
    public function resetPassword(User $user): JsonResponse
    {
        abort_if($user->is_admin, 403, 'Kata sandi admin tidak dapat direset dari sini.');

        $temp = User::randomPassword();
        $user->forceFill([
            'password' => $temp,
            'temp_password' => null,
            'temp_password_expires_at' => null,
            'reset_requested_at' => null,
        ])->save();

        // Keluarkan siswa dari perangkat yang masih masuk (bila sesi disimpan di database).
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        // Kirim otomatis bila server email aktif; bila tidak, admin mendapat tautan mailto: untuk mengirim sendiri.
        // Kata sandi tetap sudah diganti, jadi admin selalu bisa memberikannya langsung ke siswa.
        $body = $user->email ? PasswordMail::body($user->name, $user->username, $temp, null) : null;
        $sent = $body && PasswordMail::send($user->email, $body);

        return response()->json([
            'password' => $temp,
            'sent' => $sent,
            'mailEnabled' => PasswordMail::enabled(),
            'mailError' => $sent ? null : PasswordMail::$lastError,
            'mailLink' => $body && !$sent ? PasswordMail::link($user->email, $body) : null,
        ]);
    }

    /** Admin menghapus akun siswa beserta semua laporannya; siswa yang sedang masuk otomatis keluar. */
    public function destroy(User $user): JsonResponse
    {
        abort_if($user->is_admin, 403, 'Akun admin tidak dapat dihapus.');

        $name = $user->name;
        $count = DB::transaction(function () use ($user) {
            $count = $user->reports()->delete();
            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $user->delete();

            return $count;
        });

        return response()->json([
            'message' => $count ? "Akun {$name} dan {$count} laporannya sudah dihapus." : "Akun {$name} sudah dihapus.",
            'deletedReports' => $count,
        ]);
    }
}
