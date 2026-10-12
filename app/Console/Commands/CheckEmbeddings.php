<?php

namespace App\Console\Commands;

use App\Ai\Embedder;
use Illuminate\Console\Command;
use Throwable;

/**
 * A quick manual check that embeddings work end to end:
 * php artisan knowbase:check-embeddings "Any sentence"
 */
class CheckEmbeddings extends Command
{
    protected $signature = 'knowbase:check-embeddings {text=Hello from Knowbase}';

    protected $description = 'Embed a sentence with the configured provider and show the result';

    public function handle(Embedder $embedder): int
    {
        $provider = config('ai.default_for_embeddings');

        $this->info("Embedding with [{$provider}]...");

        try {
            $vector = $embedder->query($this->argument('text'));
        } catch (Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Success: received '.count($vector).' numbers.');
        $this->line('First five: '.implode(', ', array_map(
            fn (float $number): string => (string) round($number, 4),
            array_slice($vector, 0, 5),
        )));

        return self::SUCCESS;
    }
}
