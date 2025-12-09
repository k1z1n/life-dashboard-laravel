<?php

use App\Http\Controllers\Telegram\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Telegram webhook with rate limiting
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'webhook'])
    ->middleware('throttle:telegram')
    ->name('telegram.webhook');
