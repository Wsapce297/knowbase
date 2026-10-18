<?php

namespace App\Ai;

/**
 * What the assistant replied, plus the sources it was given.
 * Source [1] is $sources[0], source [2] is $sources[1], and so on.
 */
final readonly class Answer
{
    /**
     * @param  list<SearchResult>  $sources
     */
    public function __construct(
        public string $text,
        public array $sources,
        public ?string $provider = null, // which provider answered (null = no model was needed)
    ) {}

    /**
     * Source numbers the text actually cites, e.g. "[1]", "[1][3]" or "[1, 3]".
     * Numbers that don't match a source are ignored.
     *
     * @return list<int>
     */
    public function citedNumbers(): array
    {
        preg_match_all('/\[(\d+(?:\s*,\s*\d+)*)\]/', $this->text, $matches);

        $numbers = [];

        foreach ($matches[1] as $group) {
            foreach (explode(',', $group) as $number) {
                $number = (int) trim($number);

                if ($number >= 1 && $number <= count($this->sources)) {
                    $numbers[$number] = $number;
                }
            }
        }

        sort($numbers);

        return array_values($numbers);
    }
}
