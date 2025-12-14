<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestYandexGPT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yandex-gpt:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тест подключения к Yandex GPT API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiKey = config('yandex-gpt.api_key');
        $folderId = config('yandex-gpt.folder_id');
        $endpoint = config('yandex-gpt.endpoint');
        $model = config('yandex-gpt.model');

        // Проверка наличия настроек
        if (!$apiKey) {
            $this->error('❌ YANDEX_GPT_API_KEY не настроен в .env');
            $this->info('💡 Добавь в .env: YANDEX_GPT_API_KEY=твой_ключ');
            return 1;
        }

        if (!$folderId) {
            $this->error('❌ YANDEX_GPT_FOLDER_ID не настроен в .env');
            $this->info('💡 Добавь в .env: YANDEX_GPT_FOLDER_ID=твой_folder_id');
            return 1;
        }

        $this->info('🔍 Тестирование подключения к Yandex GPT API...');
        $this->newLine();
        $this->info("📋 Настройки:");
        $this->line("   Endpoint: {$endpoint}");
        $this->line("   Model: {$model}");
        $this->line("   Folder ID: {$folderId}");
        $this->line("   API Key: " . substr($apiKey, 0, 10) . '...');
        $this->newLine();

        try {
            $this->info('📤 Отправка запроса...');

            $response = Http::timeout(config('yandex-gpt.timeout', 30))
                ->withHeaders([
                    'Authorization' => "Api-Key {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'modelUri' => "gpt://{$folderId}/{$model}",
                    'completionOptions' => [
                        'stream' => false,
                        'temperature' => config('yandex-gpt.temperature', 0.3),
                        'maxTokens' => config('yandex-gpt.max_tokens', 2000),
                    ],
                    'messages' => [
                        [
                            'role' => 'user',
                            'text' => 'Привет! Ответь одним словом: работает ли API?',
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['result']['alternatives'][0]['message']['text'])) {
                    $answer = $data['result']['alternatives'][0]['message']['text'];
                    
                    $this->newLine();
                    $this->info('✅ API работает!');
                    $this->info("📝 Ответ GPT: {$answer}");
                    $this->newLine();
                    
                    // Показываем статистику
                    if (isset($data['result']['usage'])) {
                        $usage = $data['result']['usage'];
                        $this->info('📊 Статистика запроса:');
                        $this->line("   Входных токенов: " . ($usage['inputTextTokens'] ?? 'N/A'));
                        $this->line("   Выходных токенов: " . ($usage['completionTokens'] ?? 'N/A'));
                        $this->line("   Всего токенов: " . ($usage['totalTokens'] ?? 'N/A'));
                    }
                    
                    return 0;
                } else {
                    $this->error('❌ Неожиданный формат ответа');
                    $this->line('Ответ: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    return 1;
                }
            } else {
                $this->error('❌ Ошибка API: HTTP ' . $response->status());
                $this->newLine();
                
                $errorBody = $response->json();
                if ($errorBody) {
                    $this->error('Детали ошибки:');
                    $this->line(json_encode($errorBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                } else {
                    $this->line('Ответ: ' . $response->body());
                }
                
                $this->newLine();
                $this->warn('💡 Возможные причины:');
                $this->line('   - Неправильный API Key');
                $this->line('   - Неправильный Folder ID');
                $this->line('   - Сервисный аккаунт не имеет прав ai.languageModels.user');
                $this->line('   - Yandex GPT API не активирован');
                $this->line('   - Превышен лимит запросов');
                
                return 1;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->error('❌ Ошибка подключения к API');
            $this->line('   ' . $e->getMessage());
            $this->newLine();
            $this->warn('💡 Проверь:');
            $this->line('   - Интернет соединение');
            $this->line('   - Правильность endpoint в конфиге');
            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Неожиданная ошибка: ' . $e->getMessage());
            $this->line('   ' . $e->getTraceAsString());
            return 1;
        }
    }
}



