<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramAuthService;
use Illuminate\Http\Request;

class TelegramAuthController extends Controller
{
    public function __construct(
        protected TelegramAuthService $authService
    ) {}

    /**
     * Generate auth link for user
     */
    public function generateAuthLink(Request $request)
    {
        try {
            $user = $request->user();

            // Check if already linked
            if ($user->hasTelegram()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telegram уже подключен',
                ], 400);
            }

            // Generate token
            $authToken = $this->authService->generateAuthToken($user->id);
            $deepLink = $this->authService->generateDeepLink($authToken->token);

            return response()->json([
                'success' => true,
                'link' => $deepLink,
                'expires_at' => $authToken->expires_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::channel('telegram')->error('Error generating auth link', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось создать ссылку для привязки',
            ], 500);
        }
    }

    /**
     * Disconnect Telegram account
     */
    public function disconnect(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasTelegram()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telegram не подключен',
                ], 400);
            }

            $this->authService->unlinkAccount($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Telegram отключен',
            ]);
        } catch (\Exception $e) {
            \Log::channel('telegram')->error('Error disconnecting Telegram', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось отключить Telegram',
            ], 500);
        }
    }

    /**
     * Get Telegram connection status
     */
    public function status(Request $request)
    {
        try {
            $user = $request->user();
            $telegramUser = $user->telegramUser;

            return response()->json([
                'connected' => $user->hasTelegram(),
                'telegram_user' => $telegramUser ? [
                    'username' => $telegramUser->telegram_username,
                    'first_name' => $telegramUser->telegram_first_name,
                    'last_name' => $telegramUser->telegram_last_name,
                    'last_activity' => $telegramUser->last_activity_at?->diffForHumans(),
                ] : null,
            ]);
        } catch (\Exception $e) {
            \Log::channel('telegram')->error('Error getting Telegram status', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'connected' => false,
                'telegram_user' => null,
            ]);
        }
    }
}
