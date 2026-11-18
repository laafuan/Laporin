<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Support\Xlsx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminReportController extends Controller
{
    private const NEXT = [
        'Menunggu' => ['Diproses', 'Ditolak'],
        'Diproses' => ['Selesai'],
    ];

    private const TANGGAPAN = [
        'Diproses' => 'Laporan disetujui dan sedang ditindaklanjuti petugas.',
        'Ditolak' => 'Laporan ditolak oleh admin.',
        'Selesai' => 'Perbaikan sudah selesai dilakukan.',
    ];

    /**
     * Admin mengubah status laporan. Saat menyetujui, admin wajib menentukan tingkat kerusakan
     * berdasarkan hasil pengecekan; saat menandai selesai, tingkatnya boleh dikoreksi.
     */
    public function status(Request $request, Report $report): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:Diproses,Ditolak,Selesai'],
            'tanggapan' => ['nullable', 'string', 'max:500'], // catatan admin untuk pelapor (opsional)
            'tingkat' => ['nullable', Rule::in(Report::TINGKAT)],
        ], [
            'tingkat.in' => 'Pilih tingkat kerusakan: Ringan, Sedang, Berat, atau Darurat.',
        ]);

        if (!in_array($data['status'], self::NEXT[$report->status] ?? [], true)) {
            return response()->json(['message' => 'Perubahan status tidak diizinkan.'], 422);
        }

        $tingkat = $data['status'] === 'Ditolak' ? $report->tingkat : ($data['tingkat'] ?? $report->tingkat);
        if ($data['status'] !== 'Ditolak' && !$tingkat) {
            return response()->json(['message' => 'Tentukan tingkat kerusakan (Ringan, Sedang, Berat, atau Darurat) sebelum menyetujui laporan.'], 422);
        }

        $catatan = trim($data['tanggapan'] ?? '');

        $report->update([
            'status' => $data['status'],
            'tingkat' => $tingkat,
            'tanggapan' => $catatan !== '' ? $catatan : self::TANGGAPAN[$data['status']],
            'approved_at' => $data['status'] === 'Diproses' ? now() : $report->approved_at,
        ]);

        return response()->json($report->toFront());
    }

    /**
     * Mengunduh laporan sebagai file Excel, sesuai filter di halaman Persetujuan:
     * rentang tanggal dikirim (from/to, tanggal lokal sekolah), status (wait = Menunggu saja), dan kata kunci.
     */
    public function export(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:wait,all'],
            'q' => ['nullable', 'string', 'max:100'],
        ], [
            'from.date_format' => 'Format tanggal awal tidak valid.',
            'to.date_format' => 'Format tanggal akhir tidak valid.',
        ]);

        $tz = config('laporin.timezone');
        $from = !empty($data['from']) ? Carbon::createFromFormat('Y-m-d', $data['from'], $tz)->startOfDay() : null;
        $to = !empty($data['to']) ? Carbon::createFromFormat('Y-m-d', $data['to'], $tz)->endOfDay() : null;
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        $q = trim($data['q'] ?? '');
        $appTz = config('app.timezone');

        $reports = Report::light()->with('user:id,name,username')
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from->copy()->setTimezone($appTz)))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to->copy()->setTimezone($appTz)))
            ->when(($data['status'] ?? 'all') === 'wait', fn ($query) => $query->where('status', 'Menunggu'))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")->orWhere('category', 'like', "%{$q}%")->orWhere('location', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%"))))
            ->orderByDesc('created_at')->get();

        $local = fn ($d) => $d ? $d->copy()->setTimezone($tz) : null;
        $rows = $reports->map(fn (Report $r) => [
            'LP-'.str_pad((string) $r->id, 4, '0', STR_PAD_LEFT),
            $local($r->created_at),
            $r->title,
            $r->category,
            $r->location,
            $r->user?->name ?? '-',
            $r->user?->username ?? '-',
            $r->tingkat ?? 'Belum dinilai',
            $r->status,
            in_array($r->status, ['Diproses', 'Selesai'], true) ? $local($r->approved_at ?? $r->updated_at) : null,
            $r->description,
            $r->status === 'Menunggu' ? '' : (string) $r->tanggapan,
        ])->all();

        $fmt = fn (Carbon $d) => $d->locale('id')->translatedFormat('j F Y');
        $periode = $from || $to
            ? 'Laporan dikirim '.($from ? $fmt($from) : 'sejak awal').' sampai '.($to ? $fmt($to) : 'sekarang')
            : 'Semua tanggal';
        $subtitle = $periode.(($data['status'] ?? 'all') === 'wait' ? ' · hanya yang Menunggu' : '').($q !== '' ? " · kata kunci \"{$q}\"" : '')
            .' · '.count($rows).' laporan · diunduh '.$fmt(now($tz)).' pukul '.now($tz)->format('H.i');

        $file = Xlsx::build('Laporan', 'Laporan kerusakan fasilitas '.config('laporin.school'), $subtitle, [
            ['No. laporan', 12], ['Tanggal dikirim', 17], ['Judul', 32], ['Kategori', 16], ['Lokasi', 18],
            ['Pelapor', 22], ['Username', 14], ['Tingkat kerusakan', 17], ['Status', 11], ['Tanggal disetujui', 17],
            ['Deskripsi', 48, true], ['Tanggapan petugas', 40, true],
        ], $rows);

        $name = 'laporan-kerusakan_'.($from ? $from->format('Y-m-d') : 'awal').'_sd_'.($to ?? now($tz))->format('Y-m-d').'.xlsx';

        return response($file, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
