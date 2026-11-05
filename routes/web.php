

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FaqCategoryController;
use App\Http\Controllers\PercakapanController;
use App\Http\Controllers\PengaduanController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PengaturanController;
use App\Http\Middleware\PreventBackHistory;

/*
|--------------------------------------------------------------------------
| Web Routes - KANCIL SAPA
|--------------------------------------------------------------------------
*/

// HALAMAN UTAMA
// Halaman login
Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

// Proses login
Route::post('/login', [AuthController::class, 'login'])
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

// FAQ — auth tapi tanpa prefix admin agar nama route tetap faq.*
Route::middleware(['auth', PreventBackHistory::class])->group(function () {
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

    // PERCAKAPAN, PENGADUAN, LAPORAN, PENGATURAN
    Route::get('/percakapan', [PercakapanController::class, 'index'])->name('percakapan');
    Route::get('/pengaduan', [PengaduanController::class, 'index'])->name('pengaduan');
    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan');
    Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan');
    Route::post('/pengaturan/profil', [PengaturanController::class, 'updateProfil'])->name('pengaturan.profil');
    Route::post('/pengaturan/password', [PengaturanController::class, 'updatePassword'])->name('pengaturan.password');
});
