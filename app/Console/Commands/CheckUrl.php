<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckUrl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'url:check {url : URL для проверки} {--timeout=10 : Таймаут в секундах}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверить доступность сайта по URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url');
        $timeout = (int) $this->option('timeout');

        // Добавляем протокол, если его нет
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        $this->info("🔍 Проверка доступности: {$url}");
        $this->newLine();

        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withOptions([
                    'verify' => false, // Отключаем проверку SSL для тестирования
                ])
                ->get($url);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            $statusCode = $response->status();
            $isSuccessful = $response->successful();

            if ($isSuccessful) {
                $this->info("✅ Сайт работает!");
                $this->line("   HTTP статус: {$statusCode}");
                $this->line("   Время ответа: {$responseTime} мс");

                // Показываем заголовки
                $headers = $response->headers();
                if (!empty($headers)) {
                    $this->newLine();
                    $this->info("📋 Заголовки ответа:");
                    foreach ($headers as $key => $value) {
                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }
                        $this->line("   {$key}: {$value}");
                    }
                }
            } else {
                $this->error("❌ Сайт недоступен или вернул ошибку");
                $this->line("   HTTP статус: {$statusCode}");
                $this->line("   Время ответа: {$responseTime} мс");

                if ($response->body()) {
                    $body = substr($response->body(), 0, 200);
                    $this->line("   Тело ответа: {$body}...");
                }
            }

            return $isSuccessful ? 0 : 1;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            $this->error("❌ Ошибка подключения");
            $this->line("   Сообщение: " . $e->getMessage());
            $this->line("   Время до таймаута: {$responseTime} мс");
            $this->newLine();
            $this->warn("💡 Возможные причины:");
            $this->line("   - Сайт не отвечает");
            $this->line("   - Превышен таймаут ({$timeout} сек)");
            $this->line("   - Неправильный URL");
            $this->line("   - Проблемы с DNS");
            $this->line("   - Сайт заблокирован или недоступен");

            return 1;
        } catch (\Exception $e) {
            $this->error("❌ Неожиданная ошибка: " . $e->getMessage());
            return 1;
        }
    }
}


