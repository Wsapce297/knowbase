<?php

namespace App\Console\Commands;

use App\Ai\DocumentSearch;
use App\Ai\SearchResult;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Try the search from the terminal to see what the chat model would receive.
 *
 * Usage: php artisan knowbase:search "Acme Support" "How do refunds work?"
 *        php artisan knowbase:search 1 "How do refunds work?" --min=0.3 --limit=10
 */
class SearchDocuments extends Command
{
    protected $signature = 'knowbase:search
        {team : Team id or exact team name}
        {question : What to search for}
        {--limit=5 : How many passages to return}
        {--min=0.5 : Minimum similarity, from 0 to 1}';

    protected $description = "Search a team's documents the same way the chat will";

    public function handle(DocumentSearch $search): int
    {
        $teamInput = (string) $this->argument('team');

        $team = is_numeric($teamInput)
            ? Team::find((int) $teamInput)
            : Team::where('name', $teamInput)->first();

        if (! $team) {
            $this->components->error("No team found for \"{$teamInput}\".");

            return self::FAILURE;
        }

        $results = $search->search(
            $team,
            (string) $this->argument('question'),
            limit: (int) $this->option('limit'),
            minSimilarity: (float) $this->option('min'),
        );

        if ($results === []) {
            $this->components->warn('No passages were similar enough. Try a lower --min or upload more documents.');

            return self::SUCCESS;
        }

        $this->components->info(count($results).' passage(s) found in "'.$team->name.'":');

        $this->table(
            ['#', 'Similarity', 'Document', 'Page', 'Passage'],
            array_map(fn (SearchResult $result, int $index): array => [
                $index + 1,
                number_format($result->similarity, 3),
                $result->documentTitle,
                $result->page ?? '-',
                Str::limit(Str::squish($result->content), 70),
            ], $results, array_keys($results)),
        );

        return self::SUCCESS;
    }
}
