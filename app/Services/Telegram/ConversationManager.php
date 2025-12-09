<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;

class ConversationManager
{
    protected string $prefix;
    protected int $ttl;

    public function __construct()
    {
        $this->prefix = config('telegram.cache.prefix');
        $this->ttl = config('telegram.cache.ttl');
    }

    /**
     * Get conversation state
     */
    public function getState(int $chatId): ?array
    {
        return Cache::get($this->getKey($chatId));
    }

    /**
     * Set conversation state
     */
    public function setState(int $chatId, string $state, array $data = []): void
    {
        $stateData = [
            'state' => $state,
            'data' => $data,
            'created_at' => now()->timestamp,
        ];

        Cache::put($this->getKey($chatId), $stateData, $this->ttl);
    }

    /**
     * Update conversation data
     */
    public function updateData(int $chatId, array $data): void
    {
        $currentState = $this->getState($chatId);

        if ($currentState) {
            $currentState['data'] = array_merge($currentState['data'], $data);
            Cache::put($this->getKey($chatId), $currentState, $this->ttl);
        }
    }

    /**
     * Clear conversation state
     */
    public function clearState(int $chatId): void
    {
        Cache::forget($this->getKey($chatId));
    }

    /**
     * Check if user is in conversation
     */
    public function hasState(int $chatId): bool
    {
        return Cache::has($this->getKey($chatId));
    }

    /**
     * Get current state name
     */
    public function getCurrentState(int $chatId): ?string
    {
        $state = $this->getState($chatId);
        return $state['state'] ?? null;
    }

    /**
     * Get conversation data
     */
    public function getData(int $chatId): array
    {
        $state = $this->getState($chatId);
        return $state['data'] ?? [];
    }

    /**
     * Get cache key
     */
    protected function getKey(int $chatId): string
    {
        return $this->prefix . $chatId;
    }
}
