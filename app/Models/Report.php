<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** Status laporan yang masih berjalan (belum selesai / ditolak). */
    public const ACTIVE = ['Menunggu', 'Diproses'];

    /** Tingkat kerusakan, dari yang paling ringan. Ditentukan admin; kosong berarti belum dinilai. */
    public const TINGKAT = ['Ringan', 'Sedang', 'Berat', 'Darurat'];

    protected $attributes = ['status' => 'Menunggu'];

    protected $fillable = ['user_id', 'title', 'category', 'location', 'description', 'photo', 'has_photo', 'tingkat', 'map_x', 'map_y', 'approved_at', 'status', 'tanggapan'];

    protected function casts(): array
    {
        // user_id dipaksa angka: sebagian driver MySQL mengembalikannya sebagai teks, sehingga
        // perbandingan === dengan id pengguna (di PHP maupun JS) selalu gagal.
        return ['user_id' => 'integer', 'has_photo' => 'boolean', 'approved_at' => 'datetime'];
    }

    /** Memilih kolom tanpa foto (foto bisa besar) untuk daftar laporan. */
    public function scopeLight(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'title', 'category', 'location', 'description', 'has_photo', 'tingkat', 'map_x', 'map_y', 'approved_at', 'status', 'tanggapan', 'created_at', 'updated_at']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Bentuk data yang dibaca oleh tampilan (public/js/script.js). */
    public function toFront(): array
    {
        return [
            'id' => $this->id,
            'owner' => $this->user_id,
            'title' => $this->title,
            'category' => $this->category,
            'location' => $this->location,
            'desc' => $this->description,
            'hasPhoto' => (bool) $this->has_photo,
            'status' => $this->status,
            'tanggapan' => $this->tanggapan ?? '',
            'tingkat' => $this->tingkat,
            // Titik di denah sekolah (persen), null bila pelapor tidak memilih titik.
            'map' => $this->map_x !== null && $this->map_y !== null ? ['x' => (float) $this->map_x, 'y' => (float) $this->map_y] : null,
            // Waktu persetujuan (untuk nota); laporan lama tanpa catatan memakai waktu pembaruan terakhir.
            'approvedAt' => in_array($this->status, ['Diproses', 'Selesai'], true)
                ? (int) (($this->approved_at ?? $this->updated_at)->getTimestamp() * 1000)
                : null,
            'ts' => (int) ($this->created_at->getTimestamp() * 1000),
            'updatedTs' => (int) ($this->updated_at->getTimestamp() * 1000),
        ];
    }

    /** Bentuk data untuk pengunjung (Beranda): tanpa id pelapor, nama pelapor disingkat. */
    public function toPublic(): array
    {
        $front = $this->toFront();
        unset($front['owner'], $front['approvedAt']);
        if ($this->status === 'Menunggu') {
            $front['tanggapan'] = '';
        }

        return $front + ['reporter' => self::shortName($this->user?->name)];
    }

    /** "Budi Santoso" menjadi "Budi S.". */
    public static function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        if (!$parts || $parts[0] === '') {
            return 'Siswa';
        }

        return $parts[0] . (isset($parts[1]) ? ' ' . mb_strtoupper(mb_substr($parts[1], 0, 1)) . '.' : '');
    }
}
