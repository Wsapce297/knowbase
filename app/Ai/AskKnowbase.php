<?php

namespace App\Ai;

use App\Ai\Agents\KnowbaseAssistant;
use App\Models\Team;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use RuntimeException;

/**
 * The whole RAG answer in one place:
 * search the team's documents -> number the passages -> ask the assistant.
 */
class AskKnowbase
{
    public const NOTHING_FOUND = "I couldn't find anything about that in this team's documents. Try rephrasing your question, or upload a document that covers it.";

    public function __construct(private DocumentSearch $search) {}

    /**
     * @param  list<array{role: string, content: string}>  $history  Earlier messages, oldest first.
     */
    public function ask(Team $team, string $question, array $history = []): Answer
    {
        $sources = $this->search->search($team, $this->searchText($question, $history));

        // Nothing relevant: answer right away and save a model call (and free quota).
        if ($sources === []) {
            return new Answer(self::NOTHING_FOUND, []);
        }

        $response = (new KnowbaseAssistant($team->name))
            ->withMessages($history)
            ->prompt($this->buildPrompt($question, $sources));

        return new Answer(trim($response->text), $sources, $response->meta->provider);
    }

    /**
     * Same as ask(), but the answer arrives piece by piece: $onText is called
     * with each new bit of text as the model writes it (for the chat page).
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  callable(string): void  $onText
     */
    public function stream(Team $team, string $question, array $history, callable $onText): Answer
    {
        $sources = $this->search->search($team, $this->searchText($question, $history));

        if ($sources === []) {
            $onText(self::NOTHING_FOUND);

            return new Answer(self::NOTHING_FOUND, []);
        }

        $provider = null;

        $response = (new KnowbaseAssistant($team->name))
            ->withMessages($history)
            ->stream($this->buildPrompt($question, $sources))
            ->then(function (StreamedAgentResponse $finished) use (&$provider): void {
                $provider = $finished->meta->provider; // known once the stream has ended
            });

        // The stream is lazy: the request to the model happens while we loop.
        foreach ($response as $event) {
            if ($event instanceof TextDelta) {
                $onText($event->delta);
            }
        }

        $text = trim($response->text ?? '');

        if ($text === '') {
            throw new RuntimeException('The AI returned an empty answer.');
        }

        return new Answer($text, $sources, $provider);
    }

    /**
     * A follow-up like "and how long does it take?" means little on its own,
     * so search with the previous question too.
     *
     * @param  list<array{role: string, content: string}>  $history
     */
    private function searchText(string $question, array $history): string
    {
        $previousQuestion = null;

        foreach ($history as $message) {
            if ($message['role'] === 'user') {
                $previousQuestion = $message['content'];
            }
        }

        return $previousQuestion === null
            ? $question
            : $previousQuestion."\n".$question;
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
