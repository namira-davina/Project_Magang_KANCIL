<?php

use App\Http\Controllers\AntreanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaqCategoryController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PengaduanController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PercakapanController;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - KANCIL SAPA
|--------------------------------------------------------------------------
| Role: admin (semua), petugas (percakapan & balas), pimpinan (pengaduan, laporan).
| Webhook WhatsApp ada di routes/webhook.php.
*/

Route::redirect('/', '/login');

// HALAMAN UTAMA
// Halaman login
Route::get('/login', [AuthController::class, 'showLogin'])
    ->middleware(PreventBackHistory::class)
    ->name('login');

// Proses login
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login.submit');

// Logout
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Halaman Admin
Route::middleware(['auth', PreventBackHistory::class])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
});

Route::middleware(['auth', PreventBackHistory::class])->group(function () {

    // FAQ & KATEGORI — hanya Admin (pengelola inventaris FAQ)
    Route::middleware('role:admin')->group(function () {
        Route::get('/faq', [FaqController::class, 'index'])->name('faq.index');
        Route::get('/faq/create', [FaqController::class, 'create'])->name('faq.create');
        Route::post('/faq', [FaqController::class, 'store'])->name('faq.store');
        Route::get('/faq/{id}/edit', [FaqController::class, 'edit'])->name('faq.edit');
        Route::put('/faq/{id}', [FaqController::class, 'update'])->name('faq.update');
        Route::delete('/faq/{id}', [FaqController::class, 'destroy'])->name('faq.destroy');

        Route::get('/kategori-faq', [FaqCategoryController::class, 'index'])
            ->name('kategori-faq.index');

        Route::get('/kategori-faq/create', [FaqCategoryController::class, 'create'])
            ->name('kategori-faq.create');

        Route::post('/kategori-faq', [FaqCategoryController::class, 'store'])
            ->name('kategori-faq.store');

        Route::put('/kategori-faq/{id}', [FaqCategoryController::class, 'update'])
            ->name('kategori-faq.update');

        Route::delete('/kategori-faq/{id}', [FaqCategoryController::class, 'destroy'])
            ->name('kategori-faq.destroy');
    });

    // PERCAKAPAN (inbox WhatsApp)
    Route::get('/percakapan', [PercakapanController::class, 'index'])->name('percakapan');
    Route::get('/percakapan/data', [PercakapanController::class, 'threads'])->name('percakapan.data');
    Route::get('/percakapan/{conversation}/pesan', [PercakapanController::class, 'messages'])->name('percakapan.pesan');
    Route::get('/lampiran/{message}', [PercakapanController::class, 'attachment'])->name('lampiran');

    Route::middleware('role:admin,petugas')->group(function () {
        Route::post('/percakapan/{conversation}/balas', [PercakapanController::class, 'reply'])
            ->middleware('throttle:60,1')
            ->name('percakapan.balas');
        Route::post('/percakapan/{conversation}/ambil-alih', [PercakapanController::class, 'takeover'])->name('percakapan.ambil-alih');
        Route::post('/percakapan/{conversation}/kembalikan', [PercakapanController::class, 'release'])->name('percakapan.kembalikan');
    });

    // ANTREAN TIKET PETUGAS (FIFO) — semua role melihat, Admin/Petugas bertindak
    Route::get('/antrean', [AntreanController::class, 'index'])->name('antrean');
    Route::get('/antrean/data', [AntreanController::class, 'data'])->name('antrean.data');

    Route::middleware('role:admin,petugas')->group(function () {
        Route::post('/antrean/ambil-berikutnya', [AntreanController::class, 'claimNext'])->name('antrean.ambil-berikutnya');
        Route::post('/antrean/{ticket}/ambil', [AntreanController::class, 'claim'])->name('antrean.ambil');
        Route::post('/antrean/{ticket}/selesai', [AntreanController::class, 'resolve'])->name('antrean.selesai');
        Route::post('/antrean/{ticket}/jadikan-pengaduan', [AntreanController::class, 'toComplaint'])->name('antrean.pengaduan');
    });

    // PENGADUAN
    Route::get('/pengaduan', [PengaduanController::class, 'index'])->name('pengaduan');
    Route::put('/pengaduan/{complaint}', [PengaduanController::class, 'update'])
        ->middleware('role:admin,pimpinan')
        ->name('pengaduan.update');

    // LAPORAN
    Route::get('/laporan', [LaporanController::class, 'index'])
        ->middleware('role:admin,pimpinan')
        ->name('laporan');

    // PENGATURAN (profil sendiri)
    Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan');
    Route::post('/pengaturan/profil', [PengaturanController::class, 'updateProfil'])->name('pengaturan.profil');
    Route::post('/pengaturan/password', [PengaturanController::class, 'updatePassword'])->name('pengaturan.password');
});
