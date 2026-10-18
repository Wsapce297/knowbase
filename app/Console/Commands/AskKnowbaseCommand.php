<?php

namespace App\Console\Commands;

use App\Ai\AskKnowbase;
use App\Models\Team;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ask a question from the terminal and get the same answer the chat will give.
 *
 * Usage: php artisan knowbase:ask "Acme Support" "How long do I have to get my money back?"
 */
class AskKnowbaseCommand extends Command
{
    protected $signature = 'knowbase:ask
        {team : Team id or exact team name}
        {question : The question to ask}';

    protected $description = "Answer a question from a team's documents, with sources";

    public function handle(AskKnowbase $knowbase): int
    {
        $teamInput = (string) $this->argument('team');

        $team = is_numeric($teamInput)
            ? Team::find((int) $teamInput)
            : Team::where('name', $teamInput)->first();

        if (! $team) {
            $this->components->error("No team found for \"{$teamInput}\".");

            return self::FAILURE;
        }

        try {
            $answer = $knowbase->ask($team, (string) $this->argument('question'));
        } catch (Throwable $e) {
            $this->components->error('The question could not be answered: '.$e->getMessage());

            if ($reason = $e->getPrevious()?->getMessage()) {
                $this->line('Reason: '.$reason);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->line($answer->text);
        $this->newLine();

        if ($answer->sources === []) {
            return self::SUCCESS;
        }

        $cited = $answer->citedNumbers();

        $this->components->info('Sources'.($answer->provider ? " (answered by {$answer->provider})" : '').':');

        foreach ($answer->sources as $index => $source) {
            $number = $index + 1;

            $this->line(sprintf(
                '  [%d] %s%s, similarity %.3f%s',
                $number,
                $source->documentTitle,
                $source->page !== null ? ", page {$source->page}" : '',
                $source->similarity,
                in_array($number, $cited, true) ? '  <- cited' : '',
            ));
        }

        return self::SUCCESS;
    }
}
