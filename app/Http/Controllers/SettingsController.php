<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\MailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Admin: email pengirim untuk fitur lupa kata sandi (mis. Gmail sekolah + Sandi Aplikasi). */
class SettingsController extends Controller
{
    public function showMail(): JsonResponse
    {
        return response()->json(MailSettings::summary());
    }

    public function updateMail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:100'],
            'port' => ['required', 'integer', 'in:25,465,587,2525'],
            'username' => ['required', 'string', 'email:rfc', 'max:100'],
            'password' => ['nullable', 'string', 'max:200'],
            'from_name' => ['nullable', 'string', 'max:60'],
        ], [
            'host.required' => 'Isi server SMTP, mis. smtp.gmail.com.',
            'port.in' => 'Port SMTP biasanya 587 (atau 465).',
            'username.required' => 'Isi alamat email pengirim.',
            'username.email' => 'Alamat email pengirim tidak valid.',
        ]);
        $password = isset($data['password']) ? preg_replace('/\s+/', '', $data['password']) : null; // Sandi Aplikasi Google sering disalin dengan spasi
        $settings = [
            'host' => trim($data['host']),
            'port' => (int) $data['port'],
            'username' => strtolower(trim($data['username'])),
            'from_name' => trim($data['from_name'] ?? ''),
        ];
        $loginPassword = $password ?: MailSettings::storedPassword();
        if (!$loginPassword) {
            return response()->json(['message' => 'Isi Sandi Aplikasi email pengirim.'], 422);
        }

        // Sebelum disimpan, aplikasi mencoba masuk ke Gmail dengan email dan sandi ini.
        // Dengan begitu pengaturan yang salah (mis. test@gmail.com) tidak pernah tampil "Aktif".
        if ($error = MailSettings::verify($settings['host'], $settings['port'], $settings['username'], $loginPassword)) {
            return response()->json(['message' => $error], 422);
        }
        MailSettings::save($settings, $password);

        return response()->json(MailSettings::summary() + ['message' => 'Login ke server email berhasil. Email pengirim aktif dan siap mengirim kata sandi sementara.']);
    }

    public function destroyMail(): JsonResponse
    {
        Setting::write(MailSettings::KEY, null);

        return response()->json(MailSettings::summary());
    }

    /** Kirim email uji agar admin tahu pengaturannya benar sebelum dipakai siswa. */
    public function testMail(Request $request): JsonResponse
    {
        $data = $request->validate(['to' => ['required', 'string', 'email:rfc']], [
            'to.required' => 'Isi alamat email tujuan email uji.',
            'to.email' => 'Alamat email tujuan tidak valid.',
        ]);
        if (!MailSettings::apply()) {
            return response()->json(['message' => 'Simpan dulu email pengirim dan Sandi Aplikasinya.'], 422);
        }
        try {
            Mail::raw("Halo,\n\nini email uji dari aplikasi Laporin. Bila email ini masuk, fitur lupa kata sandi sudah bisa mengirim kata sandi sementara ke email siswa.\n\nSalam,\nLaporin",
                fn ($m) => $m->to($data['to'])->subject('Email uji Laporin'));
        } catch (Throwable $e) {
            $error = MailSettings::explain($e);
            MailSettings::recordResult($error);

            return response()->json(['message' => 'Email uji gagal terkirim. '.$error] + MailSettings::summary(), 422);
        }
        MailSettings::recordResult(null);

        return response()->json(['message' => "Email uji terkirim ke {$data['to']}. Periksa kotak masuk (atau folder Spam)."] + MailSettings::summary());
    }
}
