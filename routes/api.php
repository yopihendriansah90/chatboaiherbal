<?php

use App\Http\Controllers\InternalHealthController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->name('telegram.webhook');

Route::get('/internal/health', InternalHealthController::class)
    ->name('internal.health');
