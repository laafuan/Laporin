<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#181C20" media="(prefers-color-scheme: dark)">
<title>Laporin</title>
<link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\Asset::url('css/style.css') }}">
</head>
<body class="{{ $user ? ($user['admin'] ? 'is-admin' : '') : 'guest' }} splashing">

<!-- Ikon garis yang dipakai di seluruh halaman: <svg class="ico"><use href="#nama"/></svg> -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3.5 10.5 12 4l8.5 6.5"/><path d="M5.5 9v11h4.5v-6h4v6h4.5V9"/></symbol>
  <symbol id="i-approve" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="17" rx="1.5"/><path d="M9 3.5h6v2.5H9z"/><path d="m9 13 2.2 2.2L15.5 11"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8.5" r="3.2"/><path d="M3 19.5c.6-3.2 2.9-5 6-5s5.4 1.8 6 5"/><path d="M15.5 5.6a3.2 3.2 0 0 1 0 5.8"/><path d="M17.8 14.8c1.7.6 2.8 2.1 3.2 4.7"/></symbol>
  <symbol id="i-file-plus" viewBox="0 0 24 24"><path d="M14 3.5H7A1.5 1.5 0 0 0 5.5 5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8z"/><path d="M14 3.5V8h4.5"/><path d="M12 11v6M9 14h6"/></symbol>
  <symbol id="i-list" viewBox="0 0 24 24"><path d="M9 6.5h11M9 12h11M9 17.5h11"/><path d="M4.5 6.5h.01M4.5 12h.01M4.5 17.5h.01"/></symbol>
  <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.4a2.5 2.5 0 0 1 4.8.9c0 1.6-2.4 2.1-2.4 3.7"/><path d="M12 17h.01"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.8"/><path d="M4.5 20.5c.7-3.9 3.6-6 7.5-6s6.8 2.1 7.5 6"/></symbol>
  <symbol id="i-login" viewBox="0 0 24 24"><path d="M14 4.5h3.5A1.5 1.5 0 0 1 19 6v12a1.5 1.5 0 0 1-1.5 1.5H14"/><path d="M10 8l4 4-4 4"/><path d="M14 12H4"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s-6.5-5.8-6.5-11a6.5 6.5 0 0 1 13 0c0 5.2-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/></symbol>
  <symbol id="i-map" viewBox="0 0 24 24"><path d="M9 4.5 3.5 6.5v13l5.5-2 6 2 5.5-2v-13l-5.5 2z"/><path d="M9 4.5v13M15 6.5v13"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="m15.5 15.5 4 4"/></symbol>
  <symbol id="i-camera" viewBox="0 0 24 24"><path d="M4 8.5h3.2L8.8 6h6.4l1.6 2.5H20v10H4z"/><circle cx="12" cy="13.3" r="3.2"/></symbol>
  <symbol id="i-print" viewBox="0 0 24 24"><path d="M7 9V4h10v5"/><path d="M7 17H4.5V9h15v8H17"/><path d="M7 14h10v6H7z"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24"><path d="M14.5 6 8.5 12l6 6"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="1.5"/><path d="m4 6.5 8 6.5 8-6.5"/></symbol>
  <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 4v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 19.5h15"/></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4.5 7h15"/><path d="M9.5 7V4.5h5V7"/><path d="M6.5 7l1 13.5h9l1-13.5"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6.5 16v-5a5.5 5.5 0 0 1 11 0v5l1.5 2h-14z"/><path d="M10 20.5a2 2 0 0 0 4 0"/></symbol>
  <symbol id="i-tool" viewBox="0 0 24 24"><path d="M15 4.5a4.5 4.5 0 0 0-4.3 5.8L4.5 16.5l3 3 6.2-6.2A4.5 4.5 0 0 0 19.5 9l-2.7 2.7-2.6-.9-.9-2.6z"/></symbol>
  <symbol id="c-listrik" viewBox="0 0 24 24"><path d="M13 3 6 13h5.5L11 21l7-10h-5.5z"/></symbol>
  <symbol id="c-air" viewBox="0 0 24 24"><path d="M12 3.5S6.5 9.8 6.5 14a5.5 5.5 0 0 0 11 0c0-4.2-5.5-10.5-5.5-10.5z"/></symbol>
  <symbol id="c-furnitur" viewBox="0 0 24 24"><path d="M7.5 3.5h9v7.5h-9z"/><path d="M5.5 11h13v3h-13z"/><path d="M7 14v6.5M17 14v6.5"/></symbol>
  <symbol id="c-bersih" viewBox="0 0 24 24"><path d="M4.5 7h15"/><path d="M9.5 7V4.5h5V7"/><path d="M6.5 7l1 13.5h9l1-13.5"/><path d="M10 11v6M14 11v6"/></symbol>
  <symbol id="c-it" viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="11.5" rx="1"/><path d="M8.5 20h7M12 16v4"/></symbol>
  <symbol id="c-bangunan" viewBox="0 0 24 24"><path d="M3.5 20.5h17"/><path d="M5.5 20.5V9L12 4.5 18.5 9v11.5"/><path d="M10 20.5v-5h4v5"/><path d="M9.5 11.5h.01M14.5 11.5h.01"/></symbol>
  <symbol id="c-lain" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01"/></symbol>
</svg>

<!-- Layar pembuka: tampil 5 detik setiap kali aplikasi dibuka (sekali per tab) -->
<div id="splash" role="status" aria-label="Memuat Laporin">
  <div class="splash-inner">
    <img class="splash-logo" src="{{ asset('images/logo-full.jpg') }}" alt="Laporin - Siswa Melapor &amp; Beraspirasi">
    <div class="splash-bar" aria-hidden="true"><span></span></div>
    <p class="splash-text" id="splashText">Memuat laporan terbaru…</p>
    <p class="splash-sub">{{ $school['name'] }}</p>
  </div>
</div>
<script>try{ if(sessionStorage.getItem('lk_splash')){ document.getElementById('splash').remove(); document.body.classList.remove('splashing'); } }catch(e){}</script>

<!-- MASUK / DAFTAR: muncul hanya saat pengguna ingin melapor -->
<div id="auth" role="dialog" aria-modal="true" aria-label="Masuk atau daftar" onclick="if(event.target===this)closeAuth()">
  <div class="auth-wrap">
    <button type="button" class="auth-close" onclick="closeAuth()" aria-label="Tutup"><svg class="ico"><use href="#i-x"/></svg></button>
    <div class="auth-form">
      <div class="logo-plate auth-logo"><img src="{{ asset('images/logo-full.jpg') }}" alt="Laporin - Siswa Melapor &amp; Beraspirasi"></div>
      <p class="auth-sub">Akun siswa {{ $school['name'] }}</p>
      <div class="tabs">
        <button id="tabLogin" class="active" type="button" onclick="switchTab('login')">Masuk</button>
        <button id="tabRegister" type="button" onclick="switchTab('register')">Daftar</button>
      </div>

      <form id="formLogin" novalidate onsubmit="return doLogin(event)">
        <div class="note" id="loginNote" style="display:none"></div>
        <div class="err" id="errLogin"></div>
        <div class="field"><label for="loginUser">Username</label><input type="text" id="loginUser" autocomplete="username" autocapitalize="none"></div>
        <div class="field"><label for="loginPass">Kata sandi</label><div class="pw"><input type="password" id="loginPass" autocomplete="current-password"><button type="button" class="eye" aria-label="Tampilkan kata sandi" aria-pressed="false" onclick="togglePw(this)"></button></div>
          <div class="forgot-link"><a href="#" onclick="switchTab('forgot');return false;">Lupa kata sandi?</a></div></div>
        <button class="btn-primary" type="submit">Masuk</button>
        <div class="or-sep"><span>atau</span></div>
        <a class="btn-google" href="{{ url('auth/google') }}">
          <svg class="g-ico" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.5l6.8-6.8C35.9 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.5 13.3l7.9 6.2C12.3 13.5 17.7 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-2.8-.4-4.1H24v8.5h12.5c-.3 2.1-1.6 5.2-4.6 7.3l7.7 6c4.5-4.2 6.5-10.3 6.5-17.7z"/>
            <path fill="#FBBC05" d="M10.4 28.5c-.5-1.4-.8-2.9-.8-4.5s.3-3.1.8-4.5l-7.9-6.2C.9 16.5 0 20.1 0 24s.9 7.5 2.5 10.7l7.9-6.2z"/>
            <path fill="#34A853" d="M24 48c6.3 0 11.6-2.1 15.5-5.7l-7.7-6c-2.1 1.4-4.8 2.3-7.8 2.3-6.3 0-11.7-4-13.6-9.9l-7.9 6.2C6.5 42.6 14.6 48 24 48z"/>
          </svg>
          <span>Masuk dengan Google</span>
        </a>
        <div class="hint">Belum punya akun? <a href="#" onclick="switchTab('register');return false;" class="link-strong">Daftar</a></div>
      </form>

      <form id="formRegister" novalidate style="display:none" onsubmit="return doRegister(event)">
        <div class="err" id="errRegister"></div>
        <div class="field"><label for="regName">Nama lengkap</label><input type="text" id="regName" autocomplete="name"></div>
        <div class="field"><label for="regUser">Username</label><input type="text" id="regUser" autocomplete="username" autocapitalize="none"></div>
        <div class="field"><label for="regEmail">Email</label><input type="email" id="regEmail" inputmode="email" autocomplete="email" autocapitalize="none" placeholder="nama@gmail.com">
          <div class="hint left">Dipakai untuk mengirim kata sandi baru kalau kamu lupa.</div></div>
        <div class="field"><label for="regPass">Kata sandi</label><div class="pw"><input type="password" id="regPass" autocomplete="new-password"><button type="button" class="eye" aria-label="Tampilkan kata sandi" aria-pressed="false" onclick="togglePw(this)"></button></div></div>
        <button class="btn-primary" type="submit">Buat akun</button>
        <div class="or-sep"><span>atau</span></div>
        <a class="btn-google" href="{{ url('auth/google') }}">
          <svg class="g-ico" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.5l6.8-6.8C35.9 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.5 13.3l7.9 6.2C12.3 13.5 17.7 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-2.8-.4-4.1H24v8.5h12.5c-.3 2.1-1.6 5.2-4.6 7.3l7.7 6c4.5-4.2 6.5-10.3 6.5-17.7z"/>
            <path fill="#FBBC05" d="M10.4 28.5c-.5-1.4-.8-2.9-.8-4.5s.3-3.1.8-4.5l-7.9-6.2C.9 16.5 0 20.1 0 24s.9 7.5 2.5 10.7l7.9-6.2z"/>
            <path fill="#34A853" d="M24 48c6.3 0 11.6-2.1 15.5-5.7l-7.7-6c-2.1 1.4-4.8 2.3-7.8 2.3-6.3 0-11.7-4-13.6-9.9l-7.9 6.2C6.5 42.6 14.6 48 24 48z"/>
          </svg>
          <span>Buat akun dengan Google</span>
        </a>
        <div class="hint left" style="margin-top:10px">Pakai akun Google berarti kamu tidak perlu mengingat kata sandi baru. Nama dan email diambil dari akun Google-mu.</div>
        <div class="hint">Sudah punya akun? <a href="#" onclick="switchTab('login');return false;" class="link-strong">Masuk</a></div>
      </form>

      <form id="formForgot" novalidate style="display:none" onsubmit="return doForgot(event)">
        <h3 class="forgot-title">Lupa kata sandi</h3>
        <p class="hint left" style="margin:0 0 16px;">Isi username atau email akunmu. Kata sandi sementara langsung dikirim ke email yang terdaftar. Kata sandi lamamu tetap bisa dipakai sampai kata sandi sementara digunakan.</p>
        <div class="err" id="errForgot"></div>
        <div class="note" id="okForgot" style="display:none"></div>
        <div class="field"><label for="fgUser">Username atau email</label><input type="text" id="fgUser" autocomplete="username" autocapitalize="none" placeholder="budi atau budi@gmail.com"></div>
        <button class="btn-primary" type="submit">Kirim kata sandi ke email</button>
        <div class="hint"><a href="#" onclick="switchTab('login');return false;" class="link-strong">Kembali ke halaman masuk</a></div>
      </form>
    </div>
  </div>
</div>

<!-- APP -->
<div id="app" class="on">
  <div class="rail">
    <div class="mark"><img class="mark-ic" src="{{ asset('images/logo-icon.png') }}" alt=""><div><b>Laporin</b><span>{{ $school['name'] }}</span></div></div>
    <nav>
      <button data-v="home" class="active" onclick="go('home')"><svg class="ico"><use href="#i-home"/></svg> Beranda</button>
      <button data-v="admin" class="adm-only" onclick="go('admin')"><svg class="ico"><use href="#i-approve"/></svg><span class="nl-full"> Persetujuan</span><span class="nl-short">Setujui</span><span class="nav-badge" id="badgeWait" hidden></span></button>
      <button data-v="adm-users" class="adm-only" onclick="go('adm-users')"><svg class="ico"><use href="#i-users"/></svg> Pengguna<span class="nav-badge" id="badgeReset" hidden></span></button>
      <button data-v="create" class="usr-only" onclick="go('create')"><svg class="ico"><use href="#i-file-plus"/></svg><span class="nl-full"> Buat Laporan</span><span class="nl-short">Lapor</span></button>
      <button data-v="mine" class="usr-only" onclick="go('mine')"><svg class="ico"><use href="#i-list"/></svg><span class="nl-full"> Laporan Saya</span><span class="nl-short">Riwayat</span></button>
      <button data-v="info" class="usr-only" onclick="go('info')"><svg class="ico"><use href="#i-help"/></svg> Panduan</button>
      <button data-v="profile" class="auth-only" onclick="go('profile')"><svg class="ico"><use href="#i-user"/></svg> Profil</button>
      <button data-v="login" class="guest-only m-only" onclick="openAuth('login')"><svg class="ico"><use href="#i-login"/></svg> Masuk</button>
    </nav>
    <div class="userbox">
      <div class="ub auth-only-flex"><div class="avatar" id="railAv"></div><div><div class="name" id="railName">—</div>
        <div class="role" id="railRole">Siswa</div></div></div>
      <div class="guest-box guest-only-block">
        <div class="name">Belum masuk</div>
        <div class="role">Akun hanya diperlukan untuk melapor.</div>
        <button type="button" class="btn-rail" onclick="openAuth('login')"><svg class="ico"><use href="#i-login"/></svg>Masuk / Daftar</button>
      </div>
    </div>
  </div>

  <main>
    <header class="m-top"><img src="{{ asset('images/logo-icon.png') }}" alt=""><div><b>Laporin</b><span>{{ $school['name'] }}</span></div></header>

    <!-- Beranda (publik) -->
    <section class="view on" id="v-home">
      <div class="home-head">
        <div>
          <p class="kicker">{{ $school['name'] }} · Sarana dan prasarana</p>
          <h2>Laporan kerusakan fasilitas</h2>
          <p class="lede">Daftar kerusakan yang dilaporkan siswa beserta status perbaikannya. Siapa pun bisa melihat; akun hanya diperlukan untuk membuat laporan.</p>
        </div>
        <div class="head-actions">
          <button type="button" class="btn-ghost" onclick="showPublicMap()"><svg class="ico"><use href="#i-map"/></svg>Peta laporan</button>
          <button type="button" class="btn-primary" onclick="go('create')"><svg class="ico"><use href="#i-plus"/></svg>Buat laporan</button>
        </div>
      </div>

      <div class="mine-box card" id="mineBox" hidden>
        <div class="mine-head"><div><h3 id="greet">Halo</h3><p>Ringkasan laporan yang kamu kirim</p></div><button type="button" class="btn-ghost sm" onclick="go('mine')">Lihat semua</button></div>
        <div class="note warn" id="emailNote" style="display:none">Kamu belum mengisi email. <a href="#" onclick="go('profile').then(()=>editEmail(true));return false;" class="link-inherit">Isi sekarang</a> supaya kata sandi baru bisa dikirim kalau kamu lupa.</div>
        <div class="mini-stats statbar">
          <div><b id="statAll">0</b><span>Total</span></div>
          <div class="st-wait"><b id="statWait">0</b><span>Menunggu</span></div>
          <div class="st-proc"><b id="statProc">0</b><span>Diproses</span></div>
          <div class="st-done"><b id="statDone">0</b><span>Selesai</span></div>
        </div>
      </div>

      <div class="pub-stats statbar">
        <div class="stat"><div class="num" id="pubAll">–</div><div class="lbl">Laporan tercatat</div></div>
        <div class="stat st-wait"><div class="num" id="pubWait">–</div><div class="lbl">Menunggu ditinjau</div></div>
        <div class="stat st-proc"><div class="num" id="pubProc">–</div><div class="lbl">Sedang diperbaiki</div></div>
        <div class="stat st-done"><div class="num" id="pubDone">–</div><div class="lbl">Sudah selesai</div></div>
      </div>

      <div class="home-grid">
        <div class="home-main">
          <div class="feed-head">
            <h3>Laporan terbaru <span class="muted-sm">diperbarui otomatis</span></h3>
            <div class="search-wrap"><svg class="ico"><use href="#i-search"/></svg><input type="search" id="feedSearch" class="search" placeholder="Cari judul, lokasi, kategori" oninput="renderFeed()" aria-label="Cari laporan"></div>
          </div>
          <div class="chips" id="feedChips">
            <button type="button" class="chip on" data-f="all" onclick="setFeedFilter('all')">Semua</button>
            <button type="button" class="chip" data-f="Menunggu" onclick="setFeedFilter('Menunggu')">Menunggu</button>
            <button type="button" class="chip" data-f="Diproses" onclick="setFeedFilter('Diproses')">Diproses</button>
            <button type="button" class="chip" data-f="Selesai" onclick="setFeedFilter('Selesai')">Selesai</button>
          </div>
          <div id="feedList" class="feed"><div class="feed-skeleton"></div><div class="feed-skeleton"></div><div class="feed-skeleton"></div></div>
        </div>
        <aside class="home-side">
          <div class="card side-card">
            <div class="side-title">Aktivitas terakhir</div>
            <ul id="activity" class="activity"><li class="act-empty">Memuat aktivitas…</li></ul>
          </div>
          <div class="card side-card">
            <div class="side-title">Peta laporan aktif</div>
            <div class="map mini side-map" id="sideMap" onclick="showPublicMap()" title="Ketuk untuk memperbesar"><img src="{{ \App\Support\Asset::url('images/denah.jpg') }}" alt="Denah sekolah" draggable="false"><div id="sideMapPins"></div></div>
            <div class="side-hint">Tanda merah menunjukkan laporan yang belum selesai. Ketuk denah untuk memperbesar.</div>
          </div>
          <div class="card side-card">
            <div class="side-title">Belum selesai, menurut tingkat</div>
            <div id="sevBars" class="bars"></div>
          </div>
          <div class="card side-card">
            <div class="side-title">Kategori terbanyak</div>
            <div id="catBars" class="bars"></div>
          </div>
        </aside>
      </div>
    </section>

    <!-- Admin -->
    <section class="view" id="v-admin">
      <div class="pagehead"><h2>Persetujuan laporan</h2><p>Setujui atau tolak laporan dari siswa. Laporan baru masuk ke daftar ini tanpa perlu memuat ulang.</p></div>
      <div class="grid-3 g3 statbar">
        <div class="stat st-wait"><div class="num" id="aWait">0</div><div class="lbl">Menunggu</div></div>
        <div class="stat st-done"><div class="num" id="aOk">0</div><div class="lbl">Disetujui</div></div>
        <div class="stat st-rej"><div class="num" id="aNo">0</div><div class="lbl">Ditolak</div></div>
      </div>
      <div class="toolbar">
        <div class="chips"><button class="chip on" id="aChipWait" onclick="setAdm('wait')">Menunggu</button><button class="chip" id="aChipAll" onclick="setAdm('all')">Semua laporan</button></div>
        <button type="button" class="btn-ghost sm" onclick="showAllOnMap()"><svg class="ico"><use href="#i-map"/></svg>Lihat di denah</button>
      </div>
      <div class="adm-filters">
        <div class="search-wrap"><svg class="ico"><use href="#i-search"/></svg><input type="search" id="admSearch" class="search" placeholder="Cari judul, lokasi, atau nama pelapor" oninput="renderAdmin()" aria-label="Cari laporan"></div>
        <div class="date-range" title="Filter menurut tanggal laporan dikirim">
          <span class="dr-lbl">Dikirim</span>
          <input type="date" id="admFrom" onchange="renderAdmin()" aria-label="Dikirim dari tanggal">
          <span class="dr-sep">s.d.</span>
          <input type="date" id="admTo" onchange="renderAdmin()" aria-label="Dikirim sampai tanggal">
          <button type="button" class="dr-x" id="admDateClear" onclick="clearAdmDates()" aria-label="Hapus filter tanggal" hidden><svg class="ico"><use href="#i-x"/></svg></button>
        </div>
        <button type="button" class="btn-primary" id="btnExport" onclick="exportExcel()"><svg class="ico"><use href="#i-download"/></svg>Unduh Excel</button>
      </div>
      <p class="adm-count" id="admCount"></p>
      <div id="admList" class="card-grid"></div>
    </section>

    <!-- Admin: pengguna -->
    <section class="view" id="v-adm-users">
      <div class="pagehead"><h2>Pengguna</h2><p>Siswa yang sudah mendaftar. Reset kata sandi bila ada yang lupa, atau hapus akun yang tidak dipakai lagi.</p></div>
      <div class="search-wrap"><svg class="ico"><use href="#i-search"/></svg><input type="search" id="usrSearch" class="search" placeholder="Cari nama, username, atau email" oninput="renderAdmUsers()" aria-label="Cari pengguna"></div>
      <div id="admUsers" class="card-grid narrow"></div>
    </section>

    <!-- Buat laporan -->
    <section class="view" id="v-create">
      <div class="pagehead"><h2>Buat laporan</h2><p>Tulis kerusakan yang kamu temukan. Makin jelas lokasinya, makin cepat petugas datang.</p></div>
      <div class="create-grid">
        <form class="card" onsubmit="return submitReport(event)">
          <div class="formgrid">
            <div class="field full"><label for="fTitle">Judul laporan</label><input type="text" id="fTitle" placeholder="Contoh: AC kelas 9B tidak dingin" required></div>
            <div class="field"><label for="fCategory">Kategori</label>
              <select id="fCategory" required>
                <option value="">Pilih kategori</option>
                <option>Listrik & AC</option><option>Air & Sanitasi</option><option>Furnitur</option>
                <option>Kebersihan</option><option>IT & Komputer</option><option>Bangunan</option><option>Lainnya</option>
              </select>
            </div>
            <div class="field"><label for="fLocation">Lokasi</label>
              <div class="item-pick"><input type="text" id="fLocation" placeholder="Contoh: Rg 1.05" required><button type="button" class="btn-ghost" onclick="openMapPicker()"><svg class="ico"><use href="#i-pin"/></svg>Pilih di denah</button></div>
              <div class="item-found ok" id="fMapInfo" style="display:none"></div></div>
            <div class="field full"><label>Foto kerusakan <span class="req">wajib</span></label>
              <div class="filepick" id="filePick" onclick="document.getElementById('fPhoto').click()">
                <div id="fileText"><span class="fp-main"><svg class="ico"><use href="#i-camera"/></svg>Ambil atau pilih foto</span><span class="fp-sub">Foto menjadi bukti kerusakan. Ambil dari dekat supaya kerusakannya terlihat jelas.</span></div>
                <input type="file" id="fPhoto" accept="image/*" style="display:none" onchange="previewPhoto(event)">
              </div>
            </div>
            <div class="field full"><label for="fDesc">Deskripsi</label><textarea id="fDesc" placeholder="Apa yang rusak, sejak kapan, dan seberapa mengganggu?" required></textarea></div>
          </div>
          <button class="btn-primary btn-block" type="submit">Kirim laporan</button>
        </form>
        <aside class="create-side">
          <div class="card side-card tips">
            <div class="side-title">Supaya cepat ditangani</div>
            <ul class="tip-list">
              <li><b>Judul singkat.</b> Sebut barang dan masalahnya, misalnya "Kran wastafel bocor".</li>
              <li><b>Pilih titik di denah.</b> Petugas langsung tahu ruangan yang harus didatangi.</li>
              <li><b>Foto dari dekat.</b> Foto wajib dan menjadi bukti, jadi pastikan bagian yang rusak terlihat jelas.</li>
            </ul>
          </div>
          <div class="card side-card">
            <div class="side-title">Tingkat kerusakan ditentukan petugas</div>
            <p class="side-hint" style="margin:0 0 10px;">Setelah laporan dikirim, petugas mengecek kerusakannya lalu menentukan tingkatnya:</p>
            <div class="sev-legend">
              <div><span class="sev sev-Ringan"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Ringan</span><span>Masih bisa dipakai, cukup perbaikan kecil.</span></div>
              <div><span class="sev sev-Sedang"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Sedang</span><span>Fungsinya terganggu, perlu segera diperbaiki.</span></div>
              <div><span class="sev sev-Berat"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Berat</span><span>Tidak bisa dipakai sama sekali.</span></div>
              <div><span class="sev sev-Darurat"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Darurat</span><span>Berbahaya, misalnya kabel terkelupas atau kaca pecah. Jauhi area itu.</span></div>
            </div>
          </div>
        </aside>
      </div>
    </section>

    <!-- Laporan saya -->
    <section class="view" id="v-mine">
      <div class="pagehead"><h2>Laporan saya</h2><p>Semua laporan yang pernah kamu kirim. Statusnya berubah sendiri saat ditanggapi admin. Laporan bisa dihapus, kecuali yang sedang diperbaiki.</p></div>
      <div class="search-wrap"><svg class="ico"><use href="#i-search"/></svg><input type="search" id="mineSearch" class="search" placeholder="Cari judul, kategori, atau lokasi" oninput="renderMine()" aria-label="Cari laporan"></div>
      <div id="mineList" class="feed"></div>
    </section>

    <!-- Detail laporan sendiri -->
    <section class="view" id="v-detail">
      <span class="back-link" onclick="go('mine')"><svg class="ico"><use href="#i-back"/></svg>Kembali ke Laporan saya</span>
      <div class="card" id="detailBody"></div>
    </section>

    <!-- Info -->
    <section class="view" id="v-info">
      <div class="pagehead"><h2>Panduan</h2><p>Cara melapor dan apa yang terjadi setelah laporan dikirim.</p></div>
      <div class="two-col">
        <div class="card">
          <div class="section-title">Cara membuat laporan</div>
          <div class="guide-step"><div class="n">1</div><div><h4>Tekan "Lapor", lalu masuk atau daftar</h4><p>Melihat laporan di Beranda tidak perlu akun. Akun baru dibutuhkan saat kamu ingin melapor.</p></div></div>
          <div class="guide-step"><div class="n">2</div><div><h4>Isi laporan</h4><p>Judul, kategori, titik di denah, deskripsi, dan foto kerusakan (wajib, sebagai bukti).</p></div></div>
          <div class="guide-step"><div class="n">3</div><div><h4>Kirim</h4><p>Laporan langsung tampil di Beranda dengan status "Menunggu".</p></div></div>
        </div>
        <div class="card">
          <div class="section-title">Setelah laporan dikirim</div>
          <div class="guide-step"><div class="n">1</div><div><h4>Menunggu</h4><p>Laporan sudah diterima. Petugas mengecek kerusakannya dan menentukan tingkatnya: Ringan, Sedang, Berat, atau Darurat.</p></div></div>
          <div class="guide-step"><div class="n">2</div><div><h4>Diproses</h4><p>Petugas menyetujui laporan dan sedang memperbaikinya.</p></div></div>
          <div class="guide-step"><div class="n">3</div><div><h4>Selesai atau ditolak</h4><p>Laporan ditutup dengan catatan dari petugas.</p></div></div>
        </div>
      </div>
    </section>

    <!-- Profil -->
    <section class="view" id="v-profile">
      <div class="pagehead"><h2>Profil</h2><p>Data akun dan pengaturan.</p></div>
      <div class="profile-grid">
        <div class="profile-col">
          <div class="card">
            <div class="pp-head">
              <div class="pp-wrap"><div class="avatar big" id="ppBig"></div>
                <button type="button" class="pp-cam" onclick="document.getElementById('ppFile').click()" aria-label="Ganti foto profil"><svg class="ico"><use href="#i-camera"/></svg></button>
                <input type="file" id="ppFile" accept="image/*" style="display:none" onchange="setPhoto(event)"></div>
              <div class="pp-info"><div class="pp-name" id="ppName"></div><div class="pp-sub">Ketuk ikon kamera untuk memasang foto profil.</div>
                <div class="pp-actions"><button type="button" class="btn-ghost" id="ppEdit" onclick="editName(true)">Ubah nama</button><button type="button" class="btn-ghost" id="ppDel" onclick="delPhoto()">Hapus foto</button></div>
                <form id="nameForm" onsubmit="return saveName(event)" style="display:none;max-width:320px;">
                  <div class="field" style="margin:8px 0 0;"><input type="text" id="nameInput" maxlength="40" aria-label="Nama lengkap" autocomplete="name"></div>
                  <div class="pp-actions"><button class="btn-primary sm" type="submit">Simpan</button><button type="button" class="btn-ghost" onclick="editName(false)">Batal</button></div>
                </form></div>
            </div>
            <div class="profile-row"><span class="k">Nama lengkap</span><span id="pName">—</span></div>
            <div class="profile-row"><span class="k">Username</span><span id="pUser">—</span></div>
            <div class="profile-row" id="pEmailRow"><span class="k">Email</span><span class="p-email"><span id="pEmail">—</span> · <a href="#" class="map-link" id="pEmailEdit" onclick="editEmail(true);return false;">Ubah</a></span></div>
            <form id="emailForm" style="display:none;padding:12px 0;border-bottom:1px solid var(--line);" onsubmit="return saveEmail(event)">
              <div class="field" style="margin-bottom:8px;"><label for="emailInput">Email</label><input type="email" id="emailInput" inputmode="email" autocomplete="email" autocapitalize="none" placeholder="nama@gmail.com"></div>
              <div class="pp-actions"><button class="btn-primary sm" type="submit">Simpan</button><button type="button" class="btn-ghost" onclick="editEmail(false)">Batal</button></div>
            </form>
            <div class="profile-row"><span class="k">Peran</span><span id="pRole">Siswa</span></div>
            <div class="profile-row" id="pTotalRow"><span class="k">Laporan dikirim</span><span id="pTotal">0</span></div>
          </div>
          <div class="card">
            <div class="section-title">Pengaturan</div>
            <div class="setting-item"><span>Mode gelap</span>
              <button class="btn-ghost sm" id="themeBtn" onclick="toggleTheme()">Aktifkan</button>
            </div>
            <div class="setting-item"><span>Keluar dari akun ini</span>
              <button class="btn-ghost sm danger" onclick="doLogout()">Keluar</button>
            </div>
          </div>
        </div>
        <div class="profile-col">
          <div class="card">
            <div class="section-title" id="pwTitle">Ubah kata sandi</div>
            <form id="pwForm" onsubmit="return savePassword(event)" novalidate>
              <div class="hint left" id="pwGoogleNote" style="display:none; margin:0 0 12px"></div>
              <div class="field"><label for="pwOld">Kata sandi lama</label><div class="pw"><input type="password" id="pwOld" autocomplete="current-password"><button type="button" class="eye" aria-label="Tampilkan kata sandi" aria-pressed="false" onclick="togglePw(this)"></button></div>
                <div class="forgot-link"><a href="#" onclick="forgotOldPw();return false;" id="forgotOldLink">Lupa kata sandi lama?</a></div></div>
              <div class="note" id="okPw" style="display:none"></div>
              <div class="field"><label for="pwNew">Kata sandi baru</label><div class="pw"><input type="password" id="pwNew" autocomplete="new-password"><button type="button" class="eye" aria-label="Tampilkan kata sandi" aria-pressed="false" onclick="togglePw(this)"></button></div></div>
              <div class="field"><label for="pwNew2">Ulangi kata sandi baru</label><div class="pw"><input type="password" id="pwNew2" autocomplete="new-password"><button type="button" class="eye" aria-label="Tampilkan kata sandi" aria-pressed="false" onclick="togglePw(this)"></button></div></div>
              <div class="err" id="errPw"></div>
              <button class="btn-primary btn-block" type="submit" id="pwSubmit">Simpan kata sandi</button>
            </form>
          </div>

          <!-- Admin: email pengirim untuk lupa kata sandi -->
          <div class="card adm-only-block" id="mailCard">
            <div class="section-title">Email pengirim (lupa kata sandi)</div>
            <div class="mail-help">
              <p><b>Fungsinya:</b> Laporin memakai satu akun Gmail milik sekolah untuk mengirim email otomatis ke siswa, yaitu <b>kata sandi sementara</b> saat siswa menekan "Lupa kata sandi" atau saat admin menekan "Reset sandi". Tanpa pengaturan ini aplikasi tidak bisa mengirim email, jadi admin harus memberikan kata sandi baru secara langsung.</p>
              <details>
                <summary>Cara mengisinya (sekali saja)</summary>
                <ol>
                  <li>Siapkan akun Gmail yang benar-benar dimiliki sekolah, misalnya <i>laporin.smkn5@gmail.com</i>. Jangan memakai contoh seperti test@gmail.com.</li>
                  <li>Masuk ke akun itu, buka <a href="https://myaccount.google.com/signinoptions/twosv" target="_blank" rel="noopener">Verifikasi 2 Langkah</a>, lalu aktifkan.</li>
                  <li>Buka <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">Sandi Aplikasi</a>, beri nama "Laporin", lalu salin 16 huruf yang muncul.</li>
                  <li>Isi alamat Gmail dan 16 huruf itu di bawah, lalu tekan <b>Simpan</b>. Aplikasi langsung mencoba masuk ke Gmail; kalau ditolak, penyebabnya ditampilkan.</li>
                  <li>Tekan <b>Kirim uji</b> ke email kamu sendiri untuk memastikan email benar-benar sampai.</li>
                </ol>
              </details>
            </div>
            <div class="mail-status" id="mailStatus"></div>
            <form id="mailForm" onsubmit="return saveMail(event)" novalidate>
              <div class="formgrid">
                <div class="field full"><label for="mUser">Email pengirim (Gmail sekolah)</label><input type="email" id="mUser" autocomplete="off" autocapitalize="none" placeholder="laporin.sekolah@gmail.com"></div>
                <div class="field full"><label for="mPass">Sandi Aplikasi Google (16 huruf)</label><div class="pw"><input type="password" id="mPass" autocomplete="new-password" placeholder="abcd efgh ijkl mnop"><button type="button" class="eye" aria-label="Tampilkan sandi" aria-pressed="false" onclick="togglePw(this)"></button></div>
                  <div class="hint left">Bukan sandi Gmail biasa. Buat di akun Google → Keamanan → Verifikasi 2 Langkah → <b>Sandi aplikasi</b>. Kosongkan bila tidak ingin mengganti.</div></div>
                <div class="field"><label for="mFrom">Nama pengirim</label><input type="text" id="mFrom" maxlength="60" placeholder="Laporin"></div>
                <div class="field"><label for="mHost">Server SMTP</label><div class="item-pick"><input type="text" id="mHost" value="smtp.gmail.com"><input type="number" id="mPort" value="587" class="port" aria-label="Port"></div></div>
              </div>
              <div class="err" id="errMail"></div>
              <div class="pp-actions"><button class="btn-primary" type="submit">Simpan</button><button type="button" class="btn-ghost danger" id="mailOff" onclick="disableMail()">Matikan</button></div>
            </form>
            <div class="mail-test">
              <label for="mTo">Kirim email uji ke</label>
              <div class="item-pick"><input type="email" id="mTo" placeholder="email kamu" autocapitalize="none"><button type="button" class="btn-ghost" onclick="testMail()">Kirim uji</button></div>
              <div class="note" id="mailTestNote" style="display:none"></div>
            </div>
          </div>

          <!-- Admin: kredensial "Masuk dengan Google" -->
          <div class="card adm-only-block" id="googleCard">
            <div class="section-title">Masuk dengan Google</div>
            <div class="mail-help">
              <p><b>Fungsinya:</b> Siswa bisa masuk & daftar memakai akun Google mereka. Tanpa ini tombol "Masuk dengan Google" di halaman login tidak aktif.</p>
              <details>
                <summary>Cara mendapatkannya (sekali saja)</summary>
                <ol>
                  <li>Buka <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a> → New Project → nama <i>Laporin</i>.</li>
                  <li>Menu kiri: <b>APIs & Services</b> → <b>OAuth consent screen</b> → pilih <b>External</b> → isi nama app + email, lalu <b>Save</b>.</li>
                  <li>Di <b>Test users</b> klik <b>+ ADD USERS</b> → masukkan email yang mau dipakai uji login.</li>
                  <li>Menu kiri: <b>Credentials</b> → <b>+ CREATE CREDENTIALS</b> → <b>OAuth client ID</b> → pilih <b>Web application</b> (BUKAN Desktop app).</li>
                  <li>Name: <i>Laporin Web</i>.</li>
                  <li>Authorized JavaScript origins: <code>https://laporin.riandeva.online</code></li>
                  <li>Authorized redirect URIs: <code>https://laporin.riandeva.online/auth/google/callback</code></li>
                  <li>Klik <b>CREATE</b> → salin <b>Client ID</b> dan <b>Client Secret</b> ke kolom di bawah.</li>
                </ol>
              </details>
            </div>
            <div class="mail-status" id="googleStatus"></div>
            <form id="googleForm" onsubmit="return saveGoogle(event)" novalidate>
              <div class="formgrid">
                <div class="field full"><label for="gClient">Client ID</label><input type="text" id="gClient" autocomplete="off" autocapitalize="none" placeholder="123456789-abcdefghijklmnop.apps.googleusercontent.com"></div>
                <div class="field full"><label for="gSecret">Client Secret</label><div class="pw"><input type="password" id="gSecret" autocomplete="new-password" placeholder="GOCSPX-xxxxxxxxxxxxxxxx"><button type="button" class="eye" aria-label="Tampilkan secret" aria-pressed="false" onclick="togglePw(this)"></button></div>
                  <div class="hint left">Kosongkan bila tidak ingin mengganti secret yang sudah disimpan.</div></div>
              </div>
              <div class="err" id="errGoogle"></div>
              <div class="pp-actions"><button class="btn-primary" type="submit">Simpan</button><button type="button" class="btn-ghost danger" id="googleOff" onclick="disableGoogle()">Matikan</button></div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- Detail laporan publik -->
<div class="modal" id="feedModal" role="dialog" aria-modal="true" aria-labelledby="fmTitle" onclick="if(event.target===this)closeFeed()">
  <div class="modal-box feed-box">
    <div class="map-head"><h3 id="fmTitle"></h3><button type="button" class="map-x" onclick="closeFeed()" aria-label="Tutup"><svg class="ico"><use href="#i-x"/></svg></button></div>
    <div id="fmBody"></div>
  </div>
</div>
<div class="modal" id="removeModal" role="dialog" aria-modal="true" aria-labelledby="rmvTitle" onclick="if(event.target===this)closeRemove()">
  <div class="modal-box">
    <h3 id="rmvTitle">Hapus laporan?</h3>
    <p id="rmvText"></p>
    <div class="modal-actions"><button class="btn-ghost" onclick="closeRemove()">Batal</button><button class="btn-danger" onclick="doRemove()">Ya, hapus</button></div>
  </div>
</div>
<div class="modal" id="resetModal" role="dialog" aria-modal="true" aria-labelledby="rmTitle" onclick="if(event.target===this)closeReset()">
  <div class="modal-box">
    <h3 id="rmTitle"></h3>
    <p id="rmText"></p>
    <div class="modal-actions" id="rmActions"></div>
  </div>
</div>
<div class="modal" id="decideModal" role="dialog" aria-modal="true" aria-labelledby="dmTitle" onclick="if(event.target===this)closeDecide()">
  <div class="modal-box wide">
    <h3 id="dmTitle"></h3>
    <p id="dmText"></p>
    <div class="field" id="dmSevWrap"><label id="dmSevLbl">Tingkat kerusakan (hasil pengecekan)</label>
      <div class="seg seg4" role="radiogroup" aria-labelledby="dmSevLbl">
        <label><input type="radio" name="dmTingkat" value="Ringan" onchange="updateDecideSev()"><span><span class="sev sev-Ringan"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Ringan</span></span></label>
        <label><input type="radio" name="dmTingkat" value="Sedang" onchange="updateDecideSev()"><span><span class="sev sev-Sedang"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Sedang</span></span></label>
        <label><input type="radio" name="dmTingkat" value="Berat" onchange="updateDecideSev()"><span><span class="sev sev-Berat"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Berat</span></span></label>
        <label><input type="radio" name="dmTingkat" value="Darurat" onchange="updateDecideSev()"><span><span class="sev sev-Darurat"><span class="sev-m" aria-hidden="true"><i></i><i></i><i></i><i></i></span>Darurat</span></span></label>
      </div>
      <div class="sev-hint" id="dmSevHint"></div></div>
    <div class="field"><label for="dmNote" id="dmLabel">Catatan untuk pelapor (opsional)</label><textarea id="dmNote" maxlength="500" style="min-height:80px"></textarea></div>
    <div class="modal-actions"><button class="btn-ghost" onclick="closeDecide()">Batal</button><button class="btn-ok" id="dmYes" onclick="doDecide()">Kirim</button></div>
  </div>
</div>
<div class="modal" id="mapModal" role="dialog" aria-modal="true" aria-labelledby="mmTitle" onclick="if(event.target===this)closeMap()">
  <div class="modal-box map-box">
    <div class="map-head"><h3 id="mmTitle">Denah sekolah</h3>
      <div><button type="button" class="btn-ghost map-zoom" id="mmZoom" onclick="toggleMapZoom()">Perbesar</button><button type="button" class="map-x" onclick="closeMap()" aria-label="Tutup"><svg class="ico"><use href="#i-x"/></svg></button></div></div>
    <p id="mmText"></p>
    <div class="map-scroll" id="mapScroll"><div class="map" id="mapCanvas"><img src="{{ \App\Support\Asset::url('images/denah.jpg') }}" alt="Denah sekolah" draggable="false"><div class="map-hl" id="mapHl"></div><div id="mapPins"></div></div></div>
    <div class="modal-actions" id="mmActions"></div>
  </div>
</div>
<div class="notif-stack" id="notifStack" aria-live="polite"></div>
<div class="toast" id="toast"></div>

<div id="notaPrint" aria-hidden="true"></div>
<script>window.__USER__ = @json($user); window.__BASE__ = @json($base); window.__SCHOOL__ = @json($school); window.__DENAH__ = @json(\App\Support\Asset::url('images/denah.jpg'));</script>
<script src="{{ \App\Support\Asset::url('js/denah.js') }}"></script>
<script src="{{ \App\Support\Asset::url('js/script.js') }}"></script>
</body>
</html>
