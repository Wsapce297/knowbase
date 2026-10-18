<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

use function Laravel\Ai\agent;

/**
 * Sends one message to the configured chat model (Gemini by default)
 * and prints the reply, so you can check the API key and model name.
 *
 * Usage: php artisan knowbase:check-chat
 *        php artisan knowbase:check-chat "What is RAG in one sentence?"
 */
class CheckChat extends Command
{
    protected $signature = 'knowbase:check-chat {message? : What to send to the chat model}';

    protected $description = 'Send one message to the configured chat model and print its reply';

    public function handle(): int
    {
        $message = $this->argument('message')
            ?? 'Reply with one short sentence saying the Knowbase chat connection works.';

        $this->components->info('Asking the "'.config('ai.default').'" provider...');

        try {
            // A throwaway "anonymous" agent: just instructions, no class needed.
            $response = agent('You are a connection test for the Knowbase app. Keep every reply to one sentence.')
                ->prompt($message, timeout: 60);
        } catch (Throwable $e) {
            $this->components->error('The chat model could not be reached: '.$e->getMessage());

            // laravel/ai wraps the original error; show it, it usually explains the real cause.
            if ($reason = $e->getPrevious()?->getMessage()) {
                $this->line('Reason: '.$reason);
            }

            $this->line('Check GEMINI_API_KEY and GEMINI_TEXT_MODEL in .env, then run: php artisan config:clear');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Success: %s (%s) replied:',
            $response->meta->provider,
            $response->meta->model,
        ));

        $this->line($response->text);

        return self::SUCCESS;
    }
}
