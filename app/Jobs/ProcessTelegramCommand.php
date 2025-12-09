<?php

namespace App\Jobs;

use App\Services\Telegram\CommandHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\Message;

class ProcessTelegramCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [5, 15];
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected array $messageData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CommandHandler $commandHandler): void
    {
        try {
            $message = new Message($this->messageData);

            $chatId = $message->getChat()->id ?? null;
            $command = $message->getText();

            Log::channel('telegram')->info('Processing Telegram command', [
                'chat_id' => $chatId,
                'command' => $command,
            ]);

            $commandHandler->handle($message);

            Log::channel('telegram')->info('Command processed successfully', [
                'chat_id' => $chatId,
                'command' => $command,
            ]);

        } catch (\Exception $e) {
            Log::channel('telegram')->error('Error processing Telegram command', [
                'chat_id' => $this->messageData['chat']['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('telegram')->error('Failed to process Telegram command', [
            'chat_id' => $this->messageData['chat']['id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
