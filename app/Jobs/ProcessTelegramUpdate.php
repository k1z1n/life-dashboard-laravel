<?php

namespace App\Jobs;

use App\Jobs\ProcessTelegramCallback;
use App\Jobs\ProcessTelegramCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected array $update
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::channel('telegram')->info('Processing Telegram update', [
                'update_id' => $this->update['update_id'] ?? null,
            ]);

            // Handle callback query
            if (isset($this->update['callback_query'])) {
                ProcessTelegramCallback::dispatch($this->update['callback_query'])
                    ->onQueue('telegram-callbacks');
                return;
            }

            // Handle message
            if (isset($this->update['message'])) {
                $message = $this->update['message'];

                // Handle command
                if (isset($message['text']) && str_starts_with($message['text'], '/')) {
                    ProcessTelegramCommand::dispatch($message)
                        ->onQueue('telegram-commands');
                    return;
                }

                // Handle text message (conversation)
                // TODO: Implement conversation handling
            }

            Log::channel('telegram')->debug('Update type not handled', [
                'update' => $this->update,
            ]);

        } catch (\Exception $e) {
            Log::channel('telegram')->error('Error processing Telegram update', [
                'update_id' => $this->update['update_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw for retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('telegram')->error('Failed to process Telegram update after all retries', [
            'update_id' => $this->update['update_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
