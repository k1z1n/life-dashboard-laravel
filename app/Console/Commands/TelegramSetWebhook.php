<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:webhook
                            {action=info : Action to perform (set, remove, info)}';

    protected $description = 'Manage Telegram webhook';

    public function handle(TelegramBotService $botService): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'set':
                return $this->setWebhook($botService);
            case 'remove':
                return $this->removeWebhook($botService);
            case 'info':
            default:
                return $this->getWebhookInfo($botService);
        }
    }

    protected function setWebhook(TelegramBotService $botService): int
    {
        $webhookUrl = config('telegram.webhook.url');
        $secretToken = config('telegram.webhook.secret_token');

        if (empty($webhookUrl)) {
            $this->error('Webhook URL is not configured in config/telegram.php');
            return self::FAILURE;
        }

        $this->info("Setting webhook to: {$webhookUrl}");

        try {
            $result = $botService->setWebhook($webhookUrl, $secretToken);

            // API can return true or array with 'ok' key
            if ($result === true || ($result['ok'] ?? false)) {
                $this->info('✅ Webhook successfully set!');
                $this->line("URL: {$webhookUrl}");
                return self::SUCCESS;
            } else {
                $this->error('❌ Failed to set webhook');
                $this->error('Response: ' . json_encode($result));
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function removeWebhook(TelegramBotService $botService): int
    {
        $this->info('Removing webhook...');

        try {
            $result = $botService->removeWebhook();

            // API can return true or array with 'ok' key
            if ($result === true || ($result['ok'] ?? false)) {
                $this->info('✅ Webhook successfully removed!');
                return self::SUCCESS;
            } else {
                $this->error('❌ Failed to remove webhook');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function getWebhookInfo(TelegramBotService $botService): int
    {
        $this->info('Getting webhook info...');

        try {
            $info = $botService->getWebhookInfo();

            $this->line('');
            $this->line('Webhook Information:');
            $this->line('-------------------');
            $this->line('URL: ' . ($info['url'] ?? 'Not set'));
            $this->line('Has custom certificate: ' . ($info['has_custom_certificate'] ?? false ? 'Yes' : 'No'));
            $this->line('Pending updates: ' . ($info['pending_update_count'] ?? 0));
            $this->line('Max connections: ' . ($info['max_connections'] ?? 'N/A'));
            $this->line('Allowed updates: ' . json_encode($info['allowed_updates'] ?? []));

            if (isset($info['last_error_date'])) {
                $this->line('');
                $this->warn('Last error date: ' . date('Y-m-d H:i:s', $info['last_error_date']));
                $this->warn('Last error message: ' . ($info['last_error_message'] ?? 'N/A'));
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
