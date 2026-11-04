
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - KANCIL SAPA
|--------------------------------------------------------------------------
*/

// HALAMAN UTAMA
Route::get('/', function () {
    return redirect()->route('login');
});


// DASHBOARD
Route::get('/dashboard', function () {
    return view('dashboard.index');
})->name('dashboard');


// PERCAKAPAN WHATSAPP
Route::get('/percakapan', function () {
    return view('percakapan.index');
})->name('percakapan');


// DATABASE FAQ
Route::get('/faq', function () {
    return view('faq.index');
})->name('faq.index');


// TAMBAH FAQ
Route::get('/faq/create', function () {
    return view('faq.create');
})->name('faq.create');


// EDIT FAQ
Route::get('/faq/{id}/edit', function ($id) {
    return view('faq.edit', [
        'id' => $id
    ]);
})->name('faq.edit');

// KATEGORI FAQ
Route::get('/kategori-faq', function () {
    return view('kategori-faq.index');
})->name('kategori-faq');

Route::get('/kategori-faq/create', function () {
    return view('kategori-faq.create');
})->name('kategori-faq.create');

// PENGADUAN
Route::get('/pengaduan', function () {
    return view('pengaduan.index');
})->name('pengaduan');


// LAPORAN
Route::get('/laporan', function () {
    return view('laporan.index');
})->name('laporan');


// PENGATURAN
Route::get('/pengaturan', function () {
    return view('pengaturan.index');
})->name('pengaturan');


// LOGIN
Route::get('/login', function () {
    return view('auth.login');
})->name('login');


// PROSES LOGIN SEMENTARA
Route::post('/login', function () {
    return back()->with(
        'message',
        'Fitur autentikasi belum diaktifkan. Halaman ini masih dalam tahap pengembangan.'
    );
})->name('login.submit');
