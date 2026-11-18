<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Support\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    /** Siswa: laporan miliknya. Admin: semua laporan + daftar siswa (untuk nama dan foto pelapor). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_admin) {
            $reports = Report::light()->orderByDesc('created_at')->get()->map(fn ($r) => $r->toFront())->values();
            // Siswa yang meminta reset kata sandi ditampilkan paling atas.
            $users = User::where('is_admin', false)->withCount('reports')->orderByRaw('reset_requested_at is null')->orderBy('name')->get()->map(fn ($u) => [
                'uid' => $u->id,
                'user' => $u->username,
                'name' => $u->name,
                'photo' => $u->photo ?? '',
                'avatarUrl' => $u->photo ? '' : ($u->google_avatar ?? ''),
                'email' => $u->email ?? '',
                'google' => !empty($u->google_id),
                'resetRequested' => $u->reset_requested_at ? (int) ($u->reset_requested_at->getTimestamp() * 1000) : null,
                'count' => $u->reports_count,
            ])->values();

            return response()->json(['reports' => $reports, 'users' => $users]);
        }

        $reports = Report::light()->where('user_id', $user->id)->orderByDesc('created_at')->get()->map(fn ($r) => $r->toFront())->values();

        return response()->json(['reports' => $reports, 'users' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->is_admin, 403, 'Admin tidak membuat laporan.');

        // Tingkat kerusakan tidak diisi siswa: admin menentukannya setelah mengecek laporan.
        // Foto wajib sebagai bukti kerusakan.
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:40'],
            'location' => ['required', 'string', 'max:100'],
            'desc' => ['required', 'string', 'max:2000'],
            'photo' => ['required', 'string'],
            // Titik di denah sekolah (persen dari ukuran gambar), opsional tetapi harus berpasangan.
            'map_x' => ['nullable', 'numeric', 'between:0,100', 'required_with:map_y'],
            'map_y' => ['nullable', 'numeric', 'between:0,100', 'required_with:map_x'],
        ], [
            'map_x.*' => 'Titik lokasi di denah tidak valid.',
            'map_y.*' => 'Titik lokasi di denah tidak valid.',
            'title.required' => 'Judul laporan wajib diisi.',
            'category.required' => 'Pilih kategori sarana.',
            'location.required' => 'Lokasi wajib diisi.',
            'desc.required' => 'Deskripsi masalah wajib diisi.',
            'photo.required' => 'Foto kerusakan wajib dilampirkan sebagai bukti.',
        ]);

        $report = $request->user()->reports()->create([
            'title' => trim($data['title']),
            'category' => $data['category'],
            'location' => trim($data['location']),
            'description' => trim($data['desc']),
            'tingkat' => null,
            'map_x' => isset($data['map_x']) ? round((float) $data['map_x'], 2) : null,
            'map_y' => isset($data['map_y']) ? round((float) $data['map_y'], 2) : null,
            'photo' => $photo = Photo::clean($data['photo'] ?? null, 2800000),
            'has_photo' => $photo !== null,
            'status' => 'Menunggu',
        ]);

        return response()->json($report->toFront(), 201);
    }

    /** Foto laporan disajikan sebagai gambar biasa (hanya untuk pelapor dan admin). */
    public function photo(Request $request, Report $report): Response
    {
        $user = $request->user();
        abort_unless($user->is_admin || $report->user_id === $user->id, 403);

        return Photo::response($report->photo);
    }

    /** Pelapor boleh menghapus laporannya sendiri, kecuali yang sedang diperbaiki petugas. */
    public function destroy(Request $request, Report $report): JsonResponse
    {
        abort_unless($report->user_id === $request->user()->id, 403);

        if ($report->status === 'Diproses') {
            return response()->json(['message' => 'Laporan sedang diperbaiki petugas, jadi belum bisa dihapus. Kamu bisa menghapusnya setelah statusnya Selesai.'], 409);
        }

        $report->delete();

        return response()->json(['ok' => true]);
    }
}
