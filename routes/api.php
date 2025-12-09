<?php

use App\Http\Controllers\Telegram\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Telegram webhook
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'webhook'])
    ->name('telegram.webhook');
