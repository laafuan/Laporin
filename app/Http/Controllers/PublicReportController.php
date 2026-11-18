<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Support\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Laporan yang bisa dilihat pengunjung tanpa login di Beranda.
 * Laporan yang ditolak tidak ditampilkan, dan nama pelapor disingkat (mis. "Budi S.").
 */
class PublicReportController extends Controller
{
    public function index(): JsonResponse
    {
        $visible = fn () => Report::query()->where('status', '!=', 'Ditolak');

        $reports = $visible()->select(['id', 'user_id', 'title', 'category', 'location', 'description', 'has_photo', 'tingkat', 'map_x', 'map_y', 'status', 'tanggapan', 'created_at', 'updated_at'])
            ->with('user:id,name')
            ->orderByDesc('created_at')->limit(60)->get()
            ->map(fn ($r) => $r->toPublic())->values();

        $active = $visible()->whereIn('status', Report::ACTIVE);

        return response()->json([
            'reports' => $reports,
            'stats' => [
                'total' => $visible()->count(),
                'Menunggu' => $visible()->where('status', 'Menunggu')->count(),
                'Diproses' => $visible()->where('status', 'Diproses')->count(),
                'Selesai' => $visible()->where('status', 'Selesai')->count(),
                'tingkat' => (clone $active)->whereNotNull('tingkat')->selectRaw('tingkat, count(*) as n')->groupBy('tingkat')->pluck('n', 'tingkat'),
                'kategori' => $visible()->selectRaw('category, count(*) as n')->groupBy('category')->orderByDesc('n')->limit(5)->pluck('n', 'category'),
            ],
            'now' => (int) (now()->getTimestamp() * 1000),
        ]);
    }

    public function photo(Report $report): Response
    {
        abort_if($report->status === 'Ditolak', 404);

        return Photo::response($report->photo, 'public, max-age=3600');
    }
}
