<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YandexGptClient
{
    protected function http(): PendingRequest
    {
        $apiKey = config('yandex-gpt.api_key');

        return Http::timeout((int) config('notification-agent.timeout', config('yandex-gpt.timeout', 30)))
            ->withHeaders([
                'Authorization' => "Api-Key {$apiKey}",
                'Content-Type' => 'application/json',
            ]);
    }

    protected function restAssistantHttp(): PendingRequest
    {
        $apiKey = (string) config('yandex-gpt.api_key');
        $project = (string) config('yandex-gpt.folder_id');

        // OpenAI-compatible auth style.
        return Http::timeout((int) config('notification-agent.timeout', config('yandex-gpt.timeout', 30)))
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'OpenAI-Project' => $project,
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Completion endpoint: returns assistant text.
     *
     * @param array<int,array{role:string,text:string}> $messages
     */
    public function complete(array $messages, array $options = []): string
    {
        $endpoint = (string) config('yandex-gpt.endpoint');
        $folderId = (string) config('yandex-gpt.folder_id');
        $model = (string) config('yandex-gpt.model');

        $modelUri = (string) (config('notification-agent.model_uri') ?: "gpt://{$folderId}/{$model}");

        $payload = [
            'modelUri' => $modelUri,
            'completionOptions' => [
                'stream' => false,
                'temperature' => $options['temperature'] ?? config('notification-agent.temperature', 0),
                'maxTokens' => $options['max_tokens'] ?? config('notification-agent.max_tokens', 600),
            ],
            'messages' => $messages,
        ];

        Log::channel('ai')->debug('YandexGPT completion request', [
            'endpoint' => $endpoint,
            'model_uri' => $modelUri,
            'temperature' => $payload['completionOptions']['temperature'] ?? null,
            'max_tokens' => $payload['completionOptions']['maxTokens'] ?? null,
            'messages_preview' => array_map(function ($m) {
                $t = is_array($m) ? ($m['text'] ?? '') : '';
                return [
                    'role' => is_array($m) ? ($m['role'] ?? null) : null,
                    'text' => mb_substr((string) $t, 0, 300),
                ];
            }, $messages),
        ]);

        $response = $this->http()->post($endpoint, $payload);

        if (!$response->successful()) {
            Log::channel('ai')->error('YandexGPT completion failed', [
                'http_status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 2000),
            ]);
            throw new \RuntimeException('YandexGPT completion request failed: HTTP ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        $text = $data['result']['alternatives'][0]['message']['text'] ?? null;

        if (!is_string($text)) {
            Log::channel('ai')->error('YandexGPT completion unexpected response', [
                'http_status' => $response->status(),
                'json' => $data,
            ]);
            throw new \RuntimeException('YandexGPT completion response has unexpected format: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        Log::channel('ai')->debug('YandexGPT completion success', [
            'text_preview' => mb_substr($text, 0, 500),
            'text_len' => mb_strlen($text),
        ]);

        return $text;
    }

    /**
     * Agent endpoint: returns assistant text.
     *
     * @param array<string,mixed> $variables
     */
    public function agentComplete(string $agentId, array $variables): string
    {
        $endpoint = "https://llm.api.cloud.yandex.net/foundationModels/v1/agents/{$agentId}/completion";

        Log::channel('ai')->debug('YandexGPT agent request', [
            'endpoint' => $endpoint,
            'agent_id' => $agentId,
            'variables_preview' => array_map(function ($v) {
                if (is_string($v)) {
                    return mb_substr($v, 0, 300);
                }
                return is_scalar($v) ? $v : '[complex]';
            }, $variables),
        ]);

        $response = $this->http()->post($endpoint, [
            'variables' => $variables,
        ]);

        if (!$response->successful()) {
            Log::channel('ai')->error('YandexGPT agent failed', [
                'http_status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 2000),
            ]);
            throw new \RuntimeException('YandexGPT agent request failed: HTTP ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();
        $text = $data['result']['alternatives'][0]['message']['text'] ?? null;

        if (!is_string($text)) {
            Log::channel('ai')->error('YandexGPT agent unexpected response', [
                'http_status' => $response->status(),
                'json' => $data,
            ]);
            throw new \RuntimeException('YandexGPT agent response has unexpected format: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        Log::channel('ai')->debug('YandexGPT agent success', [
            'text_preview' => mb_substr($text, 0, 500),
            'text_len' => mb_strlen($text),
        ]);

        return $text;
    }

    /**
     * Responses API (rest-assistant): runs a saved prompt by id.
     */
    public function responsesPrompt(string $promptId, string $input): string
    {
        $baseUrl = rtrim((string) config('notification-agent.rest_assistant_base_url'), '/');
        $endpoint = "{$baseUrl}/responses";

        Log::channel('ai')->debug('YandexGPT responses request', [
            'endpoint' => $endpoint,
            'prompt_id' => $promptId,
            'input_preview' => mb_substr($input, 0, 800),
            'input_len' => mb_strlen($input),
        ]);

        $response = $this->restAssistantHttp()->post($endpoint, [
            'prompt' => [
                'id' => $promptId,
            ],
            'input' => $input,
        ]);

        if (!$response->successful()) {
            $body = (string) $response->body();
            $traceId = null;
            try {
                $json = $response->json();
                $traceId = is_array($json) ? ($json['traceId'] ?? null) : null;
            } catch (\Throwable $e) {
                // ignore
            }

            Log::channel('ai')->error('YandexGPT responses failed', [
                'http_status' => $response->status(),
                'trace_id' => $traceId,
                'body' => mb_substr($body, 0, 2000),
            ]);
            throw new \RuntimeException('YandexGPT responses request failed: HTTP ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json();

        // Yandex may return different shapes depending on API version.
        // Prefer explicit output_text if present; otherwise parse OpenAI-like "output" messages.
        $text = $data['output_text'] ?? ($data['content']['output_text'] ?? null);

        if (!is_string($text)) {
            $parts = [];

            // OpenAI Responses-like structure:
            // output: [{ content: [{ type: "output_text", text: "..." }, ...], ... }, ...]
            if (isset($data['output']) && is_array($data['output'])) {
                foreach ($data['output'] as $out) {
                    if (!is_array($out)) {
                        continue;
                    }
                    $content = $out['content'] ?? null;
                    if (!is_array($content)) {
                        continue;
                    }
                    foreach ($content as $chunk) {
                        if (!is_array($chunk)) {
                            continue;
                        }
                        if (isset($chunk['text']) && is_string($chunk['text'])) {
                            $parts[] = $chunk['text'];
                        }
                    }
                }
            }

            if ($parts !== []) {
                $text = implode("\n", $parts);
            }
        }

        if (!is_string($text)) {
            throw new \RuntimeException('YandexGPT responses response has unexpected format: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        Log::channel('ai')->debug('YandexGPT responses success', [
            'prompt_id' => $promptId,
            'text_preview' => mb_substr($text, 0, 800),
            'text_len' => mb_strlen($text),
        ]);

        return $text;
    }
}


