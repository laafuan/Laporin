<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\GoogleSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Throwable;

/** Admin: kredensial "Masuk dengan Google" (Client ID & Client Secret). */
class GoogleSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(GoogleSettings::summary());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:200'],
            'client_secret' => ['nullable', 'string', 'max:200'],
        ], [
            'client_id.required' => 'Isi Client ID dari Google Cloud Console.',
            'client_id.max' => 'Client ID terlalu panjang.',
            'client_secret.max' => 'Client Secret terlalu panjang.',
        ]);

        $secret = isset($data['client_secret']) ? preg_replace('/\s+/', '', $data['client_secret']) : null;
        if (!$secret) {
            $secret = GoogleSettings::storedSecret();
        }
        if (!$secret) {
            return response()->json(['message' => 'Isi Client Secret.'], 422);
        }

        $redirect = config('services.google.redirect');
        if ($error = GoogleSettings::verify($data['client_id'], $secret, $redirect)) {
            return response()->json(['message' => $error], 422);
        }

        GoogleSettings::save(['client_id' => trim($data['client_id'])], $secret);

        // Update config runtime agar langsung dipakai tanpa restart
        Config::set('services.google.client_id', trim($data['client_id']));
        Config::set('services.google.client_secret', $secret);

        return response()->json(GoogleSettings::summary() + [
            'message' => 'Kredensial Google diverifikasi. Masuk dengan Google sudah aktif.'
        ]);
    }

    public function destroy(): JsonResponse
    {
        Setting::write(GoogleSettings::KEY, null);
        // Bersihkan config services.google yang di-cache
        Config::set('services.google.client_id', null);
        Config::set('services.google.client_secret', null);

        return response()->json(GoogleSettings::summary());
    }
}