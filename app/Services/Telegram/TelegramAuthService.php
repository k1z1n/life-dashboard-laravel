<?php

namespace App\Services\Telegram;

use App\Exceptions\Telegram\AccountLinkException;
use App\Models\TelegramAuthToken;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramAuthService
{
    /**
     * Generate auth token for user
     */
    public function generateAuthToken(int $userId): TelegramAuthToken
    {
        // Delete old unused tokens
        TelegramAuthToken::where('user_id', $userId)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(config('telegram.auth_token.length'));
        $expiresIn = config('telegram.auth_token.expires_in');

        return TelegramAuthToken::create([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => now()->addMinutes($expiresIn),
        ]);
    }

    /**
     * Verify and use auth token
     */
    public function verifyAuthToken(string $token, int $telegramId): ?User
    {
        $authToken = TelegramAuthToken::where('token', $token)->first();

        if (!$authToken || !$authToken->isValid()) {
            return null;
        }

        // Mark token as used
        $authToken->markAsUsed();

        return $authToken->user;
    }

    /**
     * Link Telegram account to user
     */
    public function linkAccount(int $userId, array $telegramData): TelegramUser
    {
        // Check if this telegram account is already linked to another user
        $existingLink = TelegramUser::where('telegram_id', $telegramData['telegram_id'])->first();

        if ($existingLink && $existingLink->user_id !== $userId) {
            throw new AccountLinkException('This Telegram account is already linked to another user');
        }

        // Unlink if already linked
        if ($existingLink) {
            $existingLink->delete();
        }

        // Create new link
        $telegramUser = TelegramUser::create([
            'user_id' => $userId,
            'telegram_id' => $telegramData['telegram_id'],
            'telegram_username' => $telegramData['telegram_username'] ?? null,
            'telegram_first_name' => $telegramData['telegram_first_name'],
            'telegram_last_name' => $telegramData['telegram_last_name'] ?? null,
            'chat_id' => $telegramData['chat_id'],
            'is_active' => true,
            'last_activity_at' => now(),
        ]);

        // Update user telegram_id
        User::where('id', $userId)->update([
            'telegram_id' => $telegramData['telegram_id'],
        ]);

        return $telegramUser;
    }

    /**
     * Unlink Telegram account from user
     */
    public function unlinkAccount(int $userId): void
    {
        TelegramUser::where('user_id', $userId)->delete();

        User::where('id', $userId)->update([
            'telegram_id' => null,
        ]);
    }

    /**
     * Check if Telegram account is linked
     */
    public function isLinked(int $telegramId): bool
    {
        return TelegramUser::where('telegram_id', $telegramId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get user by Telegram ID
     */
    public function getUserByTelegramId(int $telegramId): ?User
    {
        $telegramUser = TelegramUser::where('telegram_id', $telegramId)
            ->where('is_active', true)
            ->first();

        return $telegramUser?->user;
    }

    /**
     * Generate deep link for auth
     */
    public function generateDeepLink(string $token): string
    {
        $botUsername = config('telegram.bot_username');
        return "https://t.me/{$botUsername}?start={$token}";
    }
}
