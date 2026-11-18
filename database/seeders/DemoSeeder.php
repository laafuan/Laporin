<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk presentasi / uji coba.
 * Jalankan: php artisan db:seed --class=DemoSeeder
 * Semua akun contoh memakai kata sandi: siswa123
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $siswa = ['siti' => 'Siti Aminah', 'budi' => 'Budi Santoso', 'andi' => 'Andi Pratama'];
        $users = [];
        foreach ($siswa as $username => $name) {
            $users[$username] = User::firstOrCreate(['username' => $username], ['name' => $name, 'password' => 'siswa123']);
        }

        // [pelapor, judul, kategori, lokasi, deskripsi, tingkat (diisi admin; kosong = belum dinilai), status, tanggapan, hari lalu]
        $laporan = [
            ['siti', 'Lampu koridor mati', 'Listrik & AC', 'Koridor lantai 1', 'Lampu mati sejak hari Senin, koridor gelap saat sore.', 'Ringan', 'Selesai', 'Perbaikan sudah selesai dilakukan.', 6],
            ['siti', 'Kran wastafel bocor', 'Air & Sanitasi', 'Toilet lantai 2', 'Air menetes terus dan lantai licin.', 'Sedang', 'Diproses', 'Laporan disetujui dan sedang ditindaklanjuti petugas.', 3],
            ['budi', 'AC lab RPL tidak dingin', 'Listrik & AC', 'Lab RPL', 'AC menyala tetapi udara tidak dingin.', null, 'Menunggu', null, 1],
            ['budi', 'Kursi patah', 'Furnitur', 'Kelas XI RPL 2', 'Dua kursi di barisan belakang patah.', null, 'Menunggu', null, 0],
            ['andi', 'Proyektor tidak menyala', 'IT & Komputer', 'Ruang multimedia', 'Lampu proyektor berkedip lalu mati.', null, 'Ditolak', 'Laporan ditolak oleh admin.', 4],
        ];

        // Titik di denah (persen dari ukuran public/images/denah.jpg), diambil dari tengah ruangan.
        $titik = [
            'Kran wastafel bocor' => [31.86, 83.60],     // WC dekat Ruang Aula
            'AC lab RPL tidak dingin' => [76.08, 93.21], // Lab. RPL 1
            'Kursi patah' => [31.86, 61.65],             // Rg 1.05
            'Proyektor tidak menyala' => [78.48, 72.65], // Lab. MM
        ];

        foreach ($laporan as [$u, $title, $category, $location, $desc, $tingkat, $status, $tanggapan, $daysAgo]) {
            [$mapX, $mapY] = $titik[$title] ?? [null, null];
            if ($existing = Report::where('user_id', $users[$u]->id)->where('title', $title)->first()) {
                $existing->map_x ??= $mapX;
                $existing->map_y ??= $mapY;
                $existing->save();
                continue;
            }
            $report = Report::create([
                'user_id' => $users[$u]->id,
                'map_x' => $mapX,
                'map_y' => $mapY,
                'title' => $title,
                'category' => $category,
                'location' => $location,
                'description' => $desc,
                'tingkat' => $tingkat,
                'has_photo' => false,
                'status' => $status,
                'tanggapan' => $tanggapan,
                'approved_at' => in_array($status, ['Diproses', 'Selesai'], true) ? now()->subDays(max($daysAgo - 1, 0)) : null,
            ]);
            $report->forceFill(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)])->save();
        }
    }
}
