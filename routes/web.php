<?php

use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GoogleSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Halaman aplikasi (satu halaman). Data pengguna yang sedang login dikirim langsung ke tampilan.
Route::get('/', function () {
    $user = Auth::user();

    return view('app', [
        'user' => $user ? AuthController::userArray($user) : null,
        // Awalan folder bila aplikasi dipasang di subfolder (mis. XAMPP: /laporin/public)
        'base' => rtrim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/'),
        'school' => ['name' => config('laporin.school'), 'city' => config('laporin.city')],
    ]);
});

// Cadangan agar redirect middleware "auth" tidak error (aplikasi memakai JSON, bukan halaman login terpisah).
Route::get('/login', fn () => redirect('/'))->name('login');

// Masuk / membuat akun memakai akun Google (OAuth 2.0).
// Memakai pengalihan halaman (bukan JSON), jadi diletakkan di luar grup "api".
Route::get('auth/google', [GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:auth')->name('google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:auth')->name('google.callback');

Route::prefix('api')->group(function () {
    // Token CSRF baru, dipakai tampilan bila sesi sudah berakhir (mis. setelah sandi direset admin).
    Route::get('csrf', fn () => response()->json(['csrf' => csrf_token()]));

    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:auth');

    // Beranda publik: laporan yang bisa dilihat pengunjung tanpa login (diperbarui otomatis tiap beberapa detik).
    // Tanpa sesi: permintaan ini dikirim terus-menerus, dan bila ikut membawa cookie sesi lama saat
    // pengguna sedang masuk, jawabannya bisa menimpa cookie sesi baru sehingga pengguna keluar sendiri.
    Route::withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])->group(function () {
        Route::get('public/reports', [PublicReportController::class, 'index']);
        Route::get('public/reports/{report}/photo', [PublicReportController::class, 'photo']);
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('reports', [ReportController::class, 'index']);
        Route::post('reports', [ReportController::class, 'store']);
        Route::get('reports/{report}/photo', [ReportController::class, 'photo']);
        Route::delete('reports/{report}', [ReportController::class, 'destroy']);
        Route::patch('reports/{report}/status', [AdminReportController::class, 'status'])->middleware(AdminOnly::class);

        Route::patch('profile/name', [ProfileController::class, 'name']);
        Route::patch('profile/password', [ProfileController::class, 'password']);
        Route::patch('profile/email', [ProfileController::class, 'email']);
        Route::post('profile/forgot-password', [ProfileController::class, 'forgotOld'])->middleware('throttle:auth');
        Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->middleware(AdminOnly::class);
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->middleware(AdminOnly::class);
        Route::put('profile/photo', [ProfileController::class, 'photo']);
        Route::delete('profile/photo', [ProfileController::class, 'destroyPhoto']);

        Route::middleware(AdminOnly::class)->group(function () {
            Route::get('reports-export', [AdminReportController::class, 'export']);
            Route::get('settings/mail', [SettingsController::class, 'showMail']);
            Route::put('settings/mail', [SettingsController::class, 'updateMail']);
            Route::delete('settings/mail', [SettingsController::class, 'destroyMail']);
            Route::post('settings/mail/test', [SettingsController::class, 'testMail']);

            Route::get('settings/google', [GoogleSettingsController::class, 'show']);
            Route::put('settings/google', [GoogleSettingsController::class, 'update']);
            Route::delete('settings/google', [GoogleSettingsController::class, 'destroy']);
        });
    });
});
