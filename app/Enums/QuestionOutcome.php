<?php

namespace App\Enums;

use App\Ai\Answer;

/**
 * How well a question was answered.
 *
 *   answered   the answer cites at least one source
 *   not_found  no document matched, so the fixed "couldn't find anything" reply was sent
 *   uncited    the AI got sources but cited none (often "the documents don't say")
 *
 * not_found and uncited both count as "unanswered" in analytics:
 * they point at gaps in the team's documents.
 */
enum QuestionOutcome: string
{
    case Answered = 'answered';
    case NotFound = 'not_found';
    case Uncited = 'uncited';

    public static function fromAnswer(Answer $answer): self
    {
        return match (true) {
            $answer->sources === [] => self::NotFound,
            $answer->citedNumbers() === [] => self::Uncited,
            default => self::Answered,
        };
    }

    /**
     * The same decision from the sources saved with an answer
     * (each has a "cited" flag), for answers saved before logging existed.
     *
     * @param  list<array<string, mixed>>  $sources
     */
    public static function fromStoredSources(array $sources): self
    {
        if ($sources === []) {
            return self::NotFound;
        }

        foreach ($sources as $source) {
            if (($source['cited'] ?? false) === true) {
                return self::Answered;
            }
        }

        return self::Uncited;
    }

    /**
     * @return list<self>
     */
    public static function unanswered(): array
    {
        return [self::NotFound, self::Uncited];
    }

    public function isAnswered(): bool
    {
        return $this === self::Answered;
    }

    public function label(): string
    {
        return match ($this) {
            self::Answered => 'Answered',
            self::NotFound => 'Nothing found',
            self::Uncited => 'No source cited',
        };
    }
}
