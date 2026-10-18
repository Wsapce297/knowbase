<?php

namespace App\Ai;

use App\Ai\Agents\KnowbaseAssistant;
use App\Models\Team;

/**
 * The whole RAG answer in one place:
 * search the team's documents -> number the passages -> ask the assistant.
 */
class AskKnowbase
{
    public const NOTHING_FOUND = "I couldn't find anything about that in this team's documents. Try rephrasing your question, or upload a document that covers it.";

    public function __construct(private DocumentSearch $search) {}

    public function ask(Team $team, string $question): Answer
    {
        $sources = $this->search->search($team, $question);

        // Nothing relevant: answer right away and save a model call (and free quota).
        if ($sources === []) {
            return new Answer(self::NOTHING_FOUND, []);
        }

        $response = (new KnowbaseAssistant($team->name))
            ->prompt($this->buildPrompt($question, $sources));

        return new Answer(trim($response->text), $sources, $response->meta->provider);
    }

    /**
     * Example of what the model receives:
     *
     *   Sources:
     *
     *   [1] Refund Policy, page 1
     *   Customers can ask for a refund within 30 days...
     *
     *   Question: How do refunds work?
     *
     * @param  list<SearchResult>  $sources
     */
    public function buildPrompt(string $question, array $sources): string
    {
        $blocks = [];

        foreach ($sources as $index => $source) {
            $label = '['.($index + 1).'] '.$source->documentTitle
                .($source->page !== null ? ", page {$source->page}" : '');

            $blocks[] = $label."\n".$source->content;
        }

        return "Sources:\n\n".implode("\n\n", $blocks)."\n\nQuestion: ".trim($question);
    }
}
