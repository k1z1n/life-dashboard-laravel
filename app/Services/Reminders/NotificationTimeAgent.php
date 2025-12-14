<?php

namespace App\Services\Reminders;

use App\Exceptions\Ai\NotificationAgentParseException;
use App\Services\Ai\YandexGptClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationTimeAgent
{
    public function __construct(
        private readonly YandexGptClient $client
    ) {}

    /**
     * @return array<int,Carbon>
     */
    public function computeSchedule(Carbon $createdAt, string $description, ?Carbon $expiresAt = null): array
    {
        $description = trim($description);
        if ($description === '') {
            return [];
        }

        $container = $this->buildContainer($createdAt, $description, $expiresAt);

        $mode = (string) config('notification-agent.mode', 'completion');

        Log::channel('ai')->info('NotificationTimeAgent computeSchedule start', [
            'mode' => $mode,
            'created_at' => $createdAt->format('Y-m-d H:i'),
            'expires_at' => $expiresAt?->format('Y-m-d H:i'),
            'description_preview' => mb_substr($description, 0, 300),
            'container_preview' => mb_substr($container, 0, 500),
        ]);

        $t0 = microtime(true);

        if ($mode === 'responses_prompt') {
            $promptId = (string) config('notification-agent.prompt_id');
            if ($promptId === '') {
                throw new \RuntimeException('NOTIFICATION_AGENT_PROMPT_ID is required for responses_prompt mode');
            }

            $raw = $this->client->responsesPrompt($promptId, $container);
        } elseif ($mode === 'agent') {
            $agentId = (string) config('notification-agent.agent_id');
            if ($agentId === '') {
                throw new \RuntimeException('NOTIFICATION_AGENT_ID is required for agent mode');
            }

            $raw = $this->client->agentComplete($agentId, [
                'created_at' => $createdAt->format('Y-m-d H:i'),
                'expires_at' => $expiresAt?->format('Y-m-d H:i') ?? 'none',
                'description' => $description,
                'input' => $container,
            ]);
        } else {
            $raw = $this->client->complete([
                ['role' => 'system', 'text' => (string) config('notification-agent.system_prompt')],
                ['role' => 'user', 'text' => $container],
            ]);
        }

        $times = $this->parseSchedule($raw);

        Log::channel('ai')->info('NotificationTimeAgent computeSchedule done', [
            'mode' => $mode,
            'duration_ms' => (int) ((microtime(true) - $t0) * 1000),
            'raw_preview' => mb_substr(str_replace(["\r\n", "\r"], "\n", $raw), 0, 800),
            'times_count' => count($times),
            'times_preview' => array_map(fn ($t) => $t->format('Y-m-d H:i'), array_slice($times, 0, 5)),
        ]);

        return $times;
    }

    protected function buildContainer(Carbon $createdAt, string $description, ?Carbon $expiresAt): string
    {
        $lines = [
            'CREATED_AT: ' . $createdAt->format('Y-m-d H:i'),
            'EXPIRES_AT: ' . ($expiresAt ? $expiresAt->format('Y-m-d H:i') : 'none'),
            'DESCRIPTION: ' . $description,
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array<int,Carbon>
     */
    public function parseSchedule(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), fn ($l) => $l !== ''));

        if ($lines === []) {
            return [];
        }

        $result = [];
        foreach ($lines as $line) {
            if (!preg_match('/^Уведомление\s+\d+:\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2})$/u', $line, $m)) {
                Log::channel('ai')->warning('NotificationTimeAgent parseSchedule invalid line', [
                    'line' => $line,
                    'raw_preview' => mb_substr($raw, 0, 800),
                ]);
                throw new NotificationAgentParseException('Invalid agent output line: ' . $line, $raw);
            }

            $dt = Carbon::createFromFormat('Y-m-d H:i', $m[1], config('app.timezone'));
            if (!$dt) {
                throw new NotificationAgentParseException('Invalid datetime in agent output: ' . $m[1], $raw);
            }
            $result[] = $dt;
        }

        return $result;
    }
}


