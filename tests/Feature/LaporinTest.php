<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\PasswordMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LaporinTest extends TestCase
{
    use RefreshDatabase;

    // PNG 1x1 piksel yang valid
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function siswa(string $username = 'budi'): User
    {
        return User::create(['name' => 'Budi Santoso', 'username' => $username, 'password' => 'rahasia1']);
    }

    private function admin(): User
    {
        return User::create(['name' => 'Administrator', 'username' => 'admin', 'password' => 'Admin1234', 'is_admin' => true]);
    }

    private function laporan(User $u, string $status = 'Menunggu'): Report
    {
        return Report::create([
            'user_id' => $u->id, 'title' => 'AC rusak', 'category' => 'Listrik & AC',
            'location' => 'Lab RPL', 'description' => 'Tidak dingin', 'status' => $status,
        ]);
    }

    /** Siswa mengirim laporan lewat API (foto wajib). */
    private function kirim(User $u, array $data = [])
    {
        return $this->actingAs($u)->postJson('/api/reports', $data + [
            'title' => 'Kran bocor', 'category' => 'Air & Sanitasi', 'location' => 'Toilet', 'desc' => 'Menetes', 'photo' => self::PNG,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Pemeriksaan login Gmail diganti agar tes tidak menghubungi server email sungguhan.
        MailSettings::$verifyUsing = fn () => null;
        PasswordMail::$lastError = null;
    }

    protected function tearDown(): void
    {
        MailSettings::$verifyUsing = null;
        parent::tearDown();
    }

    public function test_daftar_lalu_masuk_tanpa_peka_huruf_besar(): void
    {
        $this->postJson('/api/register', ['name' => 'Budi Santoso', 'username' => 'Budi', 'email' => 'budi@gmail.com', 'password' => 'rahasia1'])->assertCreated();
        $this->assertDatabaseHas('users', ['username' => 'budi', 'email' => 'budi@gmail.com']);

        $this->postJson('/api/login', ['username' => 'BUDI', 'password' => 'rahasia1'])
            ->assertOk()->assertJsonPath('user.user', 'budi')->assertJsonPath('user.admin', false);
    }

    public function test_username_ganda_dan_admin_ditolak(): void
    {
        $this->siswa();
        $this->postJson('/api/register', ['name' => 'Orang Lain', 'username' => 'BUDI', 'email' => 'lain@gmail.com', 'password' => 'abcdef'])->assertStatus(422);
        $this->postJson('/api/register', ['name' => 'Orang Lain', 'username' => 'admin', 'email' => 'lain@gmail.com', 'password' => 'abcdef'])->assertStatus(422);
    }

    public function test_sandi_salah_ditolak(): void
    {
        $this->siswa();
        $this->postJson('/api/login', ['username' => 'budi', 'password' => 'salah'])->assertStatus(422);
    }

    public function test_harus_login_untuk_melihat_laporan(): void
    {
        $this->getJson('/api/reports')->assertUnauthorized();
    }

    public function test_siswa_mengirim_laporan_dan_hanya_melihat_miliknya(): void
    {
        $budi = $this->siswa('budi');
        $siti = $this->siswa('siti');
        $this->laporan($siti);

        // Tingkat kerusakan yang dikirim siswa diabaikan: admin yang menentukannya nanti.
        $this->kirim($budi, ['tingkat' => 'Berat'])
            ->assertCreated()->assertJsonPath('hasPhoto', true)->assertJsonPath('status', 'Menunggu')
            ->assertJsonPath('tingkat', null)->assertJsonPath('approvedAt', null);

        $this->actingAs($budi)->getJson('/api/reports')->assertOk()->assertJsonCount(1, 'reports');
    }

    public function test_foto_wajib_sebagai_bukti_dan_foto_palsu_ditolak(): void
    {
        $budi = $this->siswa();
        $this->kirim($budi, ['photo' => null])->assertStatus(422)->assertJsonPath('message', 'Foto kerusakan wajib dilampirkan sebagai bukti.');
        $this->kirim($budi, ['photo' => 'data:image/png;base64,bukan-gambar'])->assertStatus(422);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_foto_laporan_hanya_untuk_pelapor_dan_admin(): void
    {
        $budi = $this->siswa('budi');
        $siti = $this->siswa('siti');
        $admin = $this->admin();
        $this->kirim($budi);
        $id = Report::first()->id;

        $this->actingAs($budi)->get("/api/reports/$id/photo")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($admin)->get("/api/reports/$id/photo")->assertOk();
        $this->actingAs($siti)->get("/api/reports/$id/photo")->assertForbidden();
    }

    public function test_pelapor_menghapus_laporannya_kecuali_yang_sedang_diperbaiki(): void
    {
        $budi = $this->siswa('budi');
        $siti = $this->siswa('siti');
        $admin = $this->admin();
        $menunggu = $this->laporan($budi);
        $diproses = $this->laporan($budi, 'Diproses');
        $selesai = $this->laporan($budi, 'Selesai');
        $ditolak = $this->laporan($budi, 'Ditolak');

        // Hanya pelapornya sendiri yang boleh menghapus.
        $this->actingAs($siti)->deleteJson("/api/reports/{$menunggu->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/reports/{$selesai->id}")->assertForbidden();

        // Yang sedang diperbaiki petugas tidak bisa dihapus.
        $this->actingAs($budi)->deleteJson("/api/reports/{$diproses->id}")->assertStatus(409);
        $this->assertDatabaseHas('reports', ['id' => $diproses->id]);

        foreach ([$menunggu, $selesai, $ditolak] as $r) {
            $this->actingAs($budi)->deleteJson("/api/reports/{$r->id}")->assertOk();
            $this->assertDatabaseMissing('reports', ['id' => $r->id]);
        }
        $this->getJson('/api/public/reports')->assertOk()->assertJsonCount(1, 'reports');
    }

    public function test_alur_status_hanya_untuk_admin(): void
    {
        $budi = $this->siswa();
        $admin = $this->admin();
        $r = $this->laporan($budi);

        $this->actingAs($budi)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Diproses'])->assertForbidden();

        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Selesai', 'tingkat' => 'Ringan'])->assertStatus(422); // belum disetujui
        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Diproses', 'tingkat' => 'Sedang'])->assertOk()->assertJsonPath('status', 'Diproses');
        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Selesai'])->assertOk()->assertJsonPath('status', 'Selesai')->assertJsonPath('tingkat', 'Sedang');
        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Ditolak'])->assertStatus(422); // sudah selesai
    }

    public function test_admin_melihat_semua_laporan_dan_daftar_siswa(): void
    {
        $this->laporan($this->siswa('budi'));
        $this->laporan($this->siswa('siti'));

        $this->actingAs($this->admin())->getJson('/api/reports')
            ->assertOk()->assertJsonCount(2, 'reports')->assertJsonCount(2, 'users');
    }

    public function test_admin_menentukan_tingkat_kerusakan_saat_menyetujui(): void
    {
        $budi = $this->siswa();
        $admin = $this->admin();
        $r = Report::find($this->kirim($budi)->assertCreated()->json('id'));

        // Menyetujui tanpa tingkat atau dengan tingkat yang tidak dikenal ditolak.
        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Diproses'])
            ->assertStatus(422)->assertJsonPath('message', 'Tentukan tingkat kerusakan (Ringan, Sedang, Berat, atau Darurat) sebelum menyetujui laporan.');
        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Diproses', 'tingkat' => 'Biasa'])
            ->assertStatus(422)->assertJsonPath('message', 'Pilih tingkat kerusakan: Ringan, Sedang, Berat, atau Darurat.');
        $this->assertSame('Menunggu', $r->fresh()->status);

        // Siswa tidak boleh menentukan tingkat lewat jalur admin.
        $this->actingAs($budi)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Diproses', 'tingkat' => 'Ringan'])->assertForbidden();

        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Diproses', 'tingkat' => 'Darurat'])
            ->assertOk()->assertJsonPath('tingkat', 'Darurat');
        // Saat ditandai selesai, tingkat boleh dikoreksi.
        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Selesai', 'tingkat' => 'Berat'])
            ->assertOk()->assertJsonPath('tingkat', 'Berat');

        // Laporan yang ditolak tidak perlu dinilai, dan statistik publik hanya menghitung yang sudah dinilai.
        $r2 = Report::find($this->kirim($budi)->json('id'));
        $this->actingAs($admin)->patchJson("/api/reports/{$r2->id}/status", ['status' => 'Ditolak'])->assertOk()->assertJsonPath('tingkat', null);
        $this->kirim($budi)->assertCreated();
        $this->getJson('/api/public/reports')->assertOk()->assertJsonPath('stats.Menunggu', 1)->assertJsonPath('stats.tingkat', []);
    }

    public function test_persetujuan_mencatat_waktu_untuk_nota(): void
    {
        $r = $this->laporan($this->siswa());
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/api/reports/{$r->id}/status", ['status' => 'Diproses', 'tingkat' => 'Ringan'])->assertOk();
        $this->assertNotNull($r->fresh()->approved_at);

        $this->actingAs($admin)->getJson('/api/reports')
            ->assertOk()->assertJson(fn ($json) => $json->has('reports.0.approvedAt')->etc());
    }

    public function test_ubah_kata_sandi(): void
    {
        $budi = $this->siswa();

        $this->actingAs($budi)->patchJson('/api/profile/password', [
            'current_password' => 'salah', 'password' => 'baru1234', 'password_confirmation' => 'baru1234',
        ])->assertStatus(422);
        $this->actingAs($budi)->patchJson('/api/profile/password', [
            'current_password' => 'rahasia1', 'password' => 'baru1234', 'password_confirmation' => 'beda',
        ])->assertStatus(422);
        $this->actingAs($budi)->patchJson('/api/profile/password', [
            'current_password' => 'rahasia1', 'password' => 'baru1234', 'password_confirmation' => 'baru1234',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'budi', 'password' => 'baru1234'])->assertOk();
    }

    public function test_admin_mereset_kata_sandi_siswa(): void
    {
        $budi = $this->siswa();
        $admin = $this->admin();

        $this->actingAs($budi)->postJson("/api/users/{$budi->id}/reset-password")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/users/{$admin->id}/reset-password")->assertForbidden();

        $temp = $this->actingAs($admin)->postJson("/api/users/{$budi->id}/reset-password")->assertOk()->json('password');
        $this->assertSame(8, strlen($temp));

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'budi', 'password' => $temp])->assertOk();
    }

    public function test_admin_menghapus_akun_siswa_beserta_laporannya(): void
    {
        $budi = $this->siswa();
        $andi = $this->siswa('andi');
        $admin = $this->admin();
        $this->laporan($budi);
        $this->laporan($budi, 'Selesai');
        $this->laporan($andi);

        // Siswa tidak boleh menghapus akun, dan akun admin tidak bisa dihapus.
        $this->actingAs($andi)->deleteJson("/api/users/{$budi->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}")->assertForbidden();

        // Sesi budi yang masih masuk ikut dihapus sehingga ia otomatis keluar.
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'sesi-budi', 'user_id' => $budi->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->deleteJson("/api/users/{$budi->id}")->assertOk()->assertJsonPath('deletedReports', 2);

        $this->assertDatabaseMissing('users', ['id' => $budi->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-budi']);
        $this->assertSame(0, Report::where('user_id', $budi->id)->count());
        $this->assertSame(1, Report::where('user_id', $andi->id)->count(), 'Laporan siswa lain tidak boleh ikut terhapus');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->actingAs($admin)->deleteJson("/api/users/{$budi->id}")->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['username' => 'budi', 'password' => 'rahasia1'])->assertStatus(422);
    }

    public function test_satu_kelas_bisa_masuk_bersamaan_dari_ip_yang_sama(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->siswa("siswa$i");
        }
        for ($i = 1; $i <= 15; $i++) {
            // Tiap siswa datang sebagai tamu (belum login) dari IP sekolah yang sama.
            $this->app['auth']->forgetGuards();
            $this->flushSession();
            $this->postJson('/api/login', ['username' => "siswa$i", 'password' => 'rahasia1'])->assertOk();
        }
    }

    public function test_tebak_sandi_berulang_dibatasi(): void
    {
        $this->siswa();
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['username' => 'budi', 'password' => 'salah'])->assertStatus(422);
        }
        $this->postJson('/api/login', ['username' => 'budi', 'password' => 'salah'])
            ->assertStatus(429)->assertJsonPath('message', 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.');
    }

    public function test_admin_bisa_memberi_alasan_penolakan(): void
    {
        $budi = $this->siswa();
        $r = $this->laporan($budi);

        $this->actingAs($this->admin())->patchJson("/api/reports/{$r->id}/status", ['status' => 'Ditolak', 'tanggapan' => '  Sudah dilaporkan kemarin.  '])
            ->assertOk()->assertJsonPath('tanggapan', 'Sudah dilaporkan kemarin.');

        $this->actingAs($budi)->getJson('/api/reports')->assertJsonPath('reports.0.tanggapan', 'Sudah dilaporkan kemarin.');
    }

    public function test_tanpa_catatan_memakai_tanggapan_bawaan(): void
    {
        $r = $this->laporan($this->siswa());

        $this->actingAs($this->admin())->patchJson("/api/reports/{$r->id}/status", ['status' => 'Diproses', 'tingkat' => 'Sedang', 'tanggapan' => null])
            ->assertOk()->assertJsonPath('tanggapan', 'Laporan disetujui dan sedang ditindaklanjuti petugas.');
    }

    public function test_halaman_utama_dan_token_csrf(): void
    {
        $this->get('/')->assertOk()->assertSee('Laporin')->assertSee(config('laporin.school'));
        $this->getJson('/api/csrf')->assertOk()->assertJsonStructure(['csrf']);
    }

    public function test_pemilik_laporan_dikenali_sebagai_angka(): void
    {
        $budi = $this->siswa();
        $this->laporan($budi);

        $this->actingAs($budi)->getJson('/api/reports')->assertJsonPath('reports.0.owner', $budi->id);
    }

    public function test_titik_lokasi_di_denah_tersimpan_dan_terlihat_admin(): void
    {
        $budi = $this->siswa();
        $this->kirim($budi, ['map_x' => 31.864, 'map_y' => 61.65])
            ->assertCreated()->assertJsonPath('map.x', 31.86)->assertJsonPath('map.y', 61.65);
        $this->kirim($budi)->assertCreated()->assertJsonPath('map', null);

        $maps = array_column($this->actingAs($this->admin())->getJson('/api/reports')->assertOk()->json('reports'), 'map');
        $this->assertContains(['x' => 31.86, 'y' => 61.65], $maps);
        $this->assertContains(null, $maps);
    }

    public function test_titik_denah_harus_valid_dan_berpasangan(): void
    {
        $budi = $this->siswa();
        $this->kirim($budi, ['map_x' => 120, 'map_y' => 50])->assertStatus(422);
        $this->kirim($budi, ['map_x' => 50])
            ->assertStatus(422)->assertJsonPath('message', 'Titik lokasi di denah tidak valid.');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_lewat_proxy_https_alamat_aset_ikut_https(): void
    {
        $proxy = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'laporin.contoh.id', 'X-Forwarded-Port' => '443'];

        $this->get('/', $proxy)->assertOk()
            ->assertSee('https://laporin.contoh.id/css/style.css', false)
            ->assertSee('https://laporin.contoh.id/js/script.js', false)
            ->assertSee('https://laporin.contoh.id/images/denah.jpg', false);
    }

    public function test_fitur_barang_sudah_dihapus(): void
    {
        // Menu Barang, kolom kode barang di formulir, dan halaman QR tidak ada lagi.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('fItemCode', $html);
        $this->assertStringNotContainsString('adm-items', $html);
        $this->assertFalse(Schema::hasTable('items'));
        $this->assertFalse(Schema::hasColumn('reports', 'item_id'));

        $this->actingAs($this->admin())->getJson('/api/items')->assertNotFound();
        $this->get('/barang/BRG-APAPUN')->assertNotFound();

        // Kiriman lama yang masih membawa item_code tetap diterima (isinya diabaikan).
        $this->kirim($this->siswa(), ['item_code' => 'BRG-LAMA1234'])->assertCreated()->assertJsonMissingPath('item');
    }

    public function test_jumlah_barang_dihapus_tingkat_hanya_admin_dan_denah_berversi(): void
    {
        $this->assertFalse(Schema::hasColumn('reports', 'jumlah'));
        $this->kirim($this->siswa(), ['jumlah' => 5, 'tingkat' => 'Darurat'])->assertCreated()
            ->assertJsonMissingPath('jumlah')->assertJsonPath('tingkat', null);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('fJumlah', $html, 'Tidak ada isian jumlah barang');
        $this->assertStringNotContainsString('name="tingkat"', $html, 'Siswa tidak memilih tingkat kerusakan');
        $this->assertStringContainsString('name="dmTingkat"', $html, 'Admin memilih tingkat saat menyetujui');
        $this->assertMatchesRegularExpression('#/images/denah\.jpg\?v=\d+#', $html, 'Denah diberi nomor versi agar tidak tersangkut cache');
    }

    /** Mailer uji: email ditampung di memori (tidak benar-benar terkirim) dan dianggap server email aktif. */
    private function pakaiMailerUji(): void
    {
        config(['mail.default' => 'uji', 'mail.mailers.uji' => ['transport' => 'array']]);
    }

    /** @return array<int, \Symfony\Component\Mailer\SentMessage> */
    private function emailTerkirim(): array
    {
        return Mail::mailer('uji')->getSymfonyTransport()->messages()->all();
    }

    public function test_daftar_wajib_email_yang_valid(): void
    {
        $dasar = ['name' => 'Siti Aminah', 'username' => 'siti', 'password' => 'rahasia1'];

        $this->postJson('/api/register', $dasar)->assertStatus(422);
        $this->postJson('/api/register', $dasar + ['email' => 'bukan-email'])
            ->assertStatus(422)->assertJsonPath('message', 'Alamat email tidak valid. Contoh: nama@gmail.com.');
        $this->postJson('/api/register', $dasar + ['email' => ' Siti.Aminah@Gmail.com '])->assertCreated();
        $this->assertDatabaseHas('users', ['username' => 'siti', 'email' => 'siti.aminah@gmail.com']);
    }

    public function test_siswa_bisa_mengubah_email(): void
    {
        $budi = $this->siswa();

        $this->actingAs($budi)->patchJson('/api/profile/email', ['email' => 'abc'])->assertStatus(422);
        $this->actingAs($budi)->patchJson('/api/profile/email', ['email' => 'Budi@Sekolah.sch.id'])
            ->assertOk()->assertJsonPath('user.email', 'budi@sekolah.sch.id');
    }

    public function test_lupa_sandi_tanpa_server_email_diteruskan_ke_admin(): void
    {
        $budi = $this->siswa();
        $budi->update(['email' => 'budi@gmail.com']);

        // Akun tidak dikenal (atau akun admin) ditolak dan tidak ada permintaan yang tercatat.
        $this->postJson('/api/forgot-password', ['username' => 'orang@lain.com'])->assertStatus(422);
        $this->admin();
        $this->postJson('/api/forgot-password', ['username' => 'admin'])->assertStatus(422);
        $this->assertNull($budi->fresh()->reset_requested_at);

        // Cukup username atau email (huruf besar tidak masalah). Email belum diatur: diteruskan ke admin.
        $this->postJson('/api/forgot-password', ['username' => 'Budi@Gmail.com'])->assertOk()->assertJsonPath('mode', 'admin')
            ->assertJsonPath('message', 'Pengiriman email belum diatur oleh admin. Permintaanmu sudah diteruskan ke admin untuk direset.');
        $this->assertNotNull($budi->fresh()->reset_requested_at);

        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->getJson('/api/reports')
            ->assertJsonPath('users.0.user', 'budi')->assertJsonPath('users.0.email', 'budi@gmail.com')
            ->assertJson(fn ($json) => $json->whereType('users.0.resetRequested', 'integer')->etc());

        // Admin mereset: kata sandi tetap diganti, admin dapat tautan mailto: berisi kata sandi baru,
        // dan permintaan dianggap selesai.
        $res = $this->actingAs($admin)->postJson("/api/users/{$budi->id}/reset-password")->assertOk()
            ->assertJsonPath('sent', false)->assertJsonPath('mailEnabled', false)->assertJsonPath('mailError', null);
        $this->assertStringStartsWith('mailto:budi@gmail.com?subject=', $res->json('mailLink'));
        $this->assertStringContainsString($res->json('password'), rawurldecode($res->json('mailLink')));
        $this->assertNull($budi->fresh()->reset_requested_at);
    }

    public function test_lupa_sandi_dikirim_otomatis_ke_email(): void
    {
        $this->pakaiMailerUji();
        $budi = $this->siswa();
        $budi->update(['email' => 'budi@gmail.com']);
        $sandiDariEmail = function (): string {
            $email = collect($this->emailTerkirim())->last()->getOriginalMessage();
            $this->assertSame('budi@gmail.com', $email->getTo()[0]->getAddress());
            preg_match('/Kata sandi : (\S+)/', $email->getTextBody(), $m);

            return $m[1];
        };

        // Cukup username: kata sandi sementara otomatis dikirim ke email yang terdaftar.
        $msg = $this->postJson('/api/forgot-password', ['username' => 'budi'])->assertOk()->assertJsonPath('mode', 'email')->json('message');
        $this->assertStringContainsString('b•••i@gmail.com', $msg, 'Siswa diberi tahu email tujuannya (disamarkan)');
        $this->assertCount(1, $this->emailTerkirim());
        $sandiDariEmail();

        // Kata sandi lama masih bisa dipakai sampai kata sandi sementara digunakan.
        $this->postJson('/api/login', ['username' => 'budi', 'password' => 'rahasia1'])->assertOk()->assertJsonPath('mustChange', false);
        $this->assertNull($budi->fresh()->temp_password, 'Login dengan sandi lama membatalkan sandi sementara');

        // Minta lagi, lalu masuk dengan kata sandi sementara: kata sandi lama tidak berlaku lagi.
        $this->app['auth']->forgetGuards(); $this->flushSession();
        $this->postJson('/api/forgot-password', ['username' => 'budi@gmail.com'])->assertOk()->assertJsonPath('mode', 'email');
        $temp = $sandiDariEmail();
        $this->app['auth']->forgetGuards(); $this->flushSession();
        $this->postJson('/api/login', ['username' => 'budi', 'password' => $temp])->assertOk()->assertJsonPath('mustChange', true);
        $this->app['auth']->forgetGuards(); $this->flushSession();
        $this->postJson('/api/login', ['username' => 'budi', 'password' => 'rahasia1'])->assertStatus(422);
    }

    public function test_admin_reset_sandi_terkirim_otomatis_ke_email(): void
    {
        $this->pakaiMailerUji();
        $budi = $this->siswa();
        $budi->update(['email' => 'budi@gmail.com']);

        $res = $this->actingAs($this->admin())->postJson("/api/users/{$budi->id}/reset-password")
            ->assertOk()->assertJsonPath('sent', true)->assertJsonPath('mailLink', null);
        $this->assertStringContainsString($res->json('password'), collect($this->emailTerkirim())->last()->getOriginalMessage()->getTextBody());
    }

    public function test_kata_sandi_sementara_kedaluwarsa(): void
    {
        $budi = $this->siswa();
        $budi->forceFill(['temp_password' => Hash::make('sementara1'), 'temp_password_expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/login', ['username' => 'budi', 'password' => 'sementara1'])->assertStatus(422);
    }

    public function test_gagal_kirim_email_diteruskan_ke_admin(): void
    {
        // Server SMTP yang sengaja tidak bisa dihubungi.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $budi = $this->siswa();
        $budi->update(['email' => 'budi@gmail.com']);

        $this->postJson('/api/forgot-password', ['username' => 'budi'])->assertOk()->assertJsonPath('mode', 'admin')
            ->assertJsonPath('message', 'Email gagal dikirim dari server sekolah. Permintaanmu sudah diteruskan ke admin untuk direset.');
        $this->assertNull($budi->fresh()->temp_password);
        $this->assertNotNull($budi->fresh()->reset_requested_at);

        // Reset oleh admin tetap berhasil (kata sandi diganti) dan admin diberi tahu kenapa email gagal.
        $res = $this->actingAs($this->admin())->postJson("/api/users/{$budi->id}/reset-password")->assertOk()
            ->assertJsonPath('sent', false)->assertJsonPath('mailEnabled', true);
        $this->assertStringStartsWith('Server tidak bisa terhubung ke server email', $res->json('mailError'));
        $this->app['auth']->forgetGuards(); $this->flushSession();
        $this->postJson('/api/login', ['username' => 'budi', 'password' => $res->json('password')])->assertOk();
    }

    public function test_lupa_sandi_akun_tanpa_email_diteruskan_ke_admin(): void
    {
        $this->pakaiMailerUji();
        $budi = $this->siswa();

        $this->postJson('/api/forgot-password', ['username' => 'budi'])->assertOk()->assertJsonPath('mode', 'admin');
        $this->assertNotNull($budi->fresh()->reset_requested_at);
        $this->assertCount(0, $this->emailTerkirim());
    }

    public function test_pesan_error_gmail_diterjemahkan(): void
    {
        $tolak = new \RuntimeException('Failed to authenticate on SMTP server with username "test@gmail.com" using the following authenticators: "LOGIN". Expected response code "235" but got code "535", with message "535-5.7.8 Username and Password not accepted."');
        $this->assertStringStartsWith('Gmail menolak login', MailSettings::explain($tolak));
        $this->assertStringStartsWith('Gmail meminta Sandi Aplikasi', MailSettings::explain(new \RuntimeException('534-5.7.9 Application-specific password required.')));
        $this->assertStringStartsWith('Server tidak bisa terhubung', MailSettings::explain(new \RuntimeException('Connection could not be established with host "smtp.gmail.com:587"')));
    }
    public function test_css_dan_js_punya_nomor_versi_agar_tidak_tersangkut_cache(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['css/style.css', 'js/script.js', 'js/denah.js'] as $file) {
            $this->assertMatchesRegularExpression('#/'.preg_quote($file, '#').'\?v=\d+"#', $html, "$file belum diberi nomor versi");
        }
    }

    public function test_siswa_dan_admin_bisa_ubah_nama(): void
    {
        $this->actingAs($this->siswa())->patchJson('/api/profile/name', ['name' => 'Budi Baru'])->assertOk()->assertJsonPath('user.name', 'Budi Baru');
        $this->actingAs($this->admin())->patchJson('/api/profile/name', ['name' => 'Pak  Kepala Sarpras'])->assertOk()->assertJsonPath('user.name', 'Pak Kepala Sarpras');
        $this->actingAs($this->siswa('siti'))->patchJson('/api/profile/name', ['name' => 'A'])->assertStatus(422);
    }

    public function test_tingkat_kerusakan_empat_level_dari_admin(): void
    {
        $budi = $this->siswa();
        $admin = $this->admin();

        foreach (['Ringan', 'Sedang', 'Berat', 'Darurat'] as $t) {
            $id = $this->kirim($budi)->assertCreated()->json('id');
            $this->actingAs($admin)->patchJson("/api/reports/{$id}/status", ['status' => 'Diproses', 'tingkat' => $t])->assertOk()->assertJsonPath('tingkat', $t);
        }
        $stats = $this->getJson('/api/public/reports')->json('stats.tingkat');
        ksort($stats);
        $this->assertEquals(['Berat' => 1, 'Darurat' => 1, 'Ringan' => 1, 'Sedang' => 1], $stats);
    }

    public function test_pengunjung_melihat_laporan_publik_tanpa_login(): void
    {
        $budi = User::create(['name' => 'Budi Santoso', 'username' => 'budi', 'password' => 'rahasia1', 'email' => 'budi@gmail.com']);
        $this->laporan($budi);                                           // Menunggu
        $r2 = $this->laporan($budi, 'Selesai'); $r2->update(['tanggapan' => 'Sudah diganti.']);
        $this->laporan($budi, 'Ditolak');                                // tidak boleh tampil

        $res = $this->getJson('/api/public/reports')->assertOk()
            ->assertJsonCount(2, 'reports')
            ->assertJsonPath('stats.total', 2)->assertJsonPath('stats.Menunggu', 1)->assertJsonPath('stats.Selesai', 1)
            ->assertJsonPath('reports.0.reporter', 'Budi S.');
        $json = $res->getContent();
        $this->assertStringNotContainsString('Santoso', $json, 'Nama lengkap pelapor tidak boleh tampil');
        $this->assertStringNotContainsString('budi@gmail.com', $json);
        $this->assertStringNotContainsString('"owner"', $json, 'Id pelapor tidak boleh tampil');
        $this->assertContains('Sudah diganti.', array_column($res->json('reports'), 'tanggapan'));
    }

    public function test_laporan_publik_tidak_menyentuh_sesi_agar_login_tidak_tertimpa(): void
    {
        // Permintaan Beranda yang berjalan bersamaan dengan proses masuk tidak boleh mengirim cookie sesi,
        // karena cookie sesi tamu yang lama bisa menimpa sesi baru sehingga pengguna keluar sendiri.
        $id = $this->kirim($this->siswa())->json('id');
        $this->app['auth']->forgetGuards(); $this->flushSession();

        $this->get('/api/public/reports')->assertOk()->assertCookieMissing(config('session.cookie'));
        $this->get("/api/public/reports/{$id}/photo")->assertOk()->assertCookieMissing(config('session.cookie'));
        $this->get('/')->assertOk()->assertCookie(config('session.cookie'));
    }

    public function test_foto_laporan_publik_kecuali_yang_ditolak(): void
    {
        $budi = $this->siswa();
        $this->kirim($budi);
        $r = Report::first();
        $this->app['auth']->forgetGuards(); $this->flushSession();

        $this->get("/api/public/reports/{$r->id}/photo")->assertOk()->assertHeader('Content-Type', 'image/png');
        $r->update(['status' => 'Ditolak']);
        $this->get("/api/public/reports/{$r->id}/photo")->assertNotFound();
    }

    public function test_admin_mengatur_email_pengirim_dan_sandinya_tidak_pernah_tampil(): void
    {
        $admin = $this->admin();

        $this->actingAs($this->siswa())->getJson('/api/settings/mail')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/settings/mail')->assertOk()->assertJsonPath('active', false);
        $this->actingAs($admin)->putJson('/api/settings/mail', ['host' => 'smtp.gmail.com', 'port' => 587, 'username' => 'sekolah@gmail.com'])
            ->assertStatus(422)->assertJsonPath('message', 'Isi Sandi Aplikasi email pengirim.');

        // Login ke Gmail ditolak: pengaturan tidak disimpan dan admin diberi tahu penyebabnya.
        $dicoba = [];
        MailSettings::$verifyUsing = function ($host, $port, $user, $pass) use (&$dicoba) {
            $dicoba[] = [$host, $port, $user, $pass];

            return 'Gmail menolak login: alamat email atau Sandi Aplikasi salah.';
        };
        $this->actingAs($admin)->putJson('/api/settings/mail', ['host' => 'smtp.gmail.com', 'port' => 587, 'username' => 'test@gmail.com', 'password' => 'sandigmailbiasa'])
            ->assertStatus(422)->assertJsonPath('message', 'Gmail menolak login: alamat email atau Sandi Aplikasi salah.');
        $this->actingAs($admin)->getJson('/api/settings/mail')->assertJsonPath('active', false)->assertJsonPath('status', 'off');

        // Login berhasil: tersimpan, aktif, dan sandi yang dicoba sudah tanpa spasi.
        MailSettings::$verifyUsing = function ($host, $port, $user, $pass) use (&$dicoba) {
            $dicoba[] = [$host, $port, $user, $pass];

            return null;
        };
        $res = $this->actingAs($admin)->putJson('/api/settings/mail', [
            'host' => 'smtp.gmail.com', 'port' => 587, 'username' => 'Sekolah@Gmail.com', 'password' => 'abcd efgh ijkl mnop', 'from_name' => 'Laporin SMK',
        ])->assertOk()->assertJsonPath('active', true)->assertJsonPath('status', 'ok')->assertJsonPath('username', 'sekolah@gmail.com')->assertJsonPath('hasPassword', true);
        $this->assertSame(['smtp.gmail.com', 587, 'sekolah@gmail.com', 'abcdefghijklmnop'], end($dicoba));
        $this->assertStringNotContainsString('abcd', $res->getContent());
        $this->assertStringNotContainsString('abcdefghijklmnop', \App\Models\Setting::query()->value('value'), 'Sandi harus tersimpan terenkripsi');

        // Simpan ulang tanpa sandi: sandi lama tetap dipakai (dan ikut diperiksa ulang)
        $this->actingAs($admin)->putJson('/api/settings/mail', ['host' => 'smtp.gmail.com', 'port' => 465, 'username' => 'sekolah@gmail.com'])
            ->assertOk()->assertJsonPath('active', true)->assertJsonPath('port', 465);
        $this->assertSame(['smtp.gmail.com', 465, 'sekolah@gmail.com', 'abcdefghijklmnop'], end($dicoba));
        $this->assertSame('abcdefghijklmnop', \App\Support\MailSettings::get()['password']);
        $this->assertTrue(\App\Support\PasswordMail::enabled(), 'Lupa sandi otomatis aktif setelah email pengirim diatur');

        $this->actingAs($admin)->deleteJson('/api/settings/mail')->assertOk()->assertJsonPath('active', false);
    }

    public function test_email_uji_melaporkan_kegagalan_dengan_jelas(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/settings/mail/test', ['to' => 'admin@sekolah.sch.id'])
            ->assertStatus(422)->assertJsonPath('message', 'Simpan dulu email pengirim dan Sandi Aplikasinya.');

        // Tersimpan, tetapi server email tidak bisa dihubungi: email uji gagal dan statusnya menjadi "bermasalah".
        $this->actingAs($admin)->putJson('/api/settings/mail', ['host' => '127.0.0.1', 'port' => 2525, 'username' => 'uji@sekolah.sch.id', 'password' => 'salah'])->assertOk();
        $msg = $this->actingAs($admin)->postJson('/api/settings/mail/test', ['to' => 'admin@sekolah.sch.id'])->assertStatus(422)->json('message');
        $this->assertStringStartsWith('Email uji gagal terkirim. Server tidak bisa terhubung', $msg);
        $this->actingAs($admin)->getJson('/api/settings/mail')->assertJsonPath('status', 'error')
            ->assertJson(fn ($json) => $json->whereType('lastError', 'string')->etc());
    }

    public function test_lupa_kata_sandi_lama_dari_profil(): void
    {
        $this->pakaiMailerUji();
        $budi = $this->siswa();
        $budi->update(['email' => 'budi@gmail.com']);

        $res = $this->actingAs($budi)->postJson('/api/profile/forgot-password')->assertOk()->assertJsonPath('sent', true);
        $this->assertStringContainsString('budi@gmail.com', $res->json('message'));
        preg_match('/Kata sandi : (\S+)/', collect($this->emailTerkirim())->last()->getOriginalMessage()->getTextBody(), $m);

        // Kata sandi sementara dipakai sebagai "kata sandi lama" untuk membuat kata sandi baru
        $this->actingAs($budi)->patchJson('/api/profile/password', ['current_password' => $m[1], 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])->assertOk();
        $this->assertNull($budi->fresh()->temp_password);
        $this->app['auth']->forgetGuards(); $this->flushSession();
        $this->postJson('/api/login', ['username' => 'budi', 'password' => 'baru12345'])->assertOk();
    }

    public function test_admin_unduh_excel_sesuai_filter_tanggal_dikirim(): void
    {
        $budi = $this->siswa();
        $admin = $this->admin();
        $buat = function (string $judul, string $waktuWib, string $status = 'Menunggu') use ($budi) {
            $r = $this->laporan($budi, $status);
            $r->title = $judul;
            $r->created_at = Carbon::parse($waktuWib, 'Asia/Jakarta')->setTimezone(config('app.timezone'));
            $r->save();
        };
        $buat('Lampu kelas mati', '2026-10-01 08:00');
        $buat('Kran wastafel bocor', '2026-10-15 23:30');
        $buat('Pintu toilet rusak', '2026-10-31 07:00', 'Selesai');

        $this->actingAs($budi)->getJson('/api/reports-export')->assertForbidden();

        $res = $this->actingAs($admin)->get('/api/reports-export?from=2026-10-10&to=2026-10-31&status=all')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('laporan-kerusakan_2026-10-10_sd_2026-10-31.xlsx', $res->headers->get('Content-Disposition'));
        $xlsx = $res->getContent();
        $this->assertStringStartsWith("PK\x03\x04", $xlsx, 'File Excel berupa arsip zip');
        $this->assertStringContainsString('Kran wastafel bocor', $xlsx);
        $this->assertStringContainsString('Pintu toilet rusak', $xlsx, 'Tanggal akhir ikut sampai pukul 23.59');
        $this->assertStringNotContainsString('Lampu kelas mati', $xlsx, 'Di luar rentang tanggal');
        $this->assertStringContainsString('Tanggal dikirim', $xlsx);

        // Tab "Menunggu" hanya berisi laporan yang menunggu; tanggal terbalik otomatis ditukar.
        $wait = $this->actingAs($admin)->get('/api/reports-export?from=2026-10-31&to=2026-10-10&status=wait')->assertOk()->getContent();
        $this->assertStringContainsString('Kran wastafel bocor', $wait);
        $this->assertStringNotContainsString('Pintu toilet rusak', $wait);

        // Struktur file: setiap bagian XML bisa dibaca.
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tmp, $xlsx);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/worksheets/sheet1.xml', 'xl/styles.xml'] as $part) {
            $xml = $zip->getFromName($part);
            $this->assertNotFalse($xml, "$part harus ada");
            $this->assertNotFalse(@simplexml_load_string($xml), "$part harus XML yang valid");
        }
        $zip->close();
        unlink($tmp);

        $this->actingAs($admin)->getJson('/api/reports-export?from=31-10-2026')->assertStatus(422);
    }

    public function test_lupa_kata_sandi_lama_tanpa_email_atau_tanpa_server_email(): void
    {
        $budi = $this->siswa();
        $this->actingAs($budi)->postJson('/api/profile/forgot-password')->assertStatus(422);

        $budi->update(['email' => 'budi@gmail.com']);
        $this->actingAs($budi)->postJson('/api/profile/forgot-password')->assertOk()->assertJsonPath('sent', false);
        $this->assertNotNull($budi->fresh()->reset_requested_at, 'Diteruskan ke admin');
    }
}
