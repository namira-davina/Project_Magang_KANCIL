<?php

use App\Http\Controllers\Webhook\BalesOtomatisWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Webhook tanpa sesi/CSRF. URL didaftarkan di member area BalesOtomatis:
| Integration > Pengaturan Webhook > https://<domain>/webhook/balesotomatis/<BALESOTOMATIS_WEBHOOK_TOKEN>
*/

Route::post('/webhook/balesotomatis/{token}', BalesOtomatisWebhookController::class)
    ->middleware('throttle:webhook')
    ->name('webhook.balesotomatis');
