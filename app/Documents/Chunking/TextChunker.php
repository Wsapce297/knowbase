<?php

namespace App\Documents\Chunking;

use App\Documents\Extraction\ExtractedPage;
use InvalidArgumentException;

/**
 * Splits extracted pages into overlapping chunks.
 *
 * How it works, in short:
 *  1. Cut each page into "pieces": paragraphs, or sentences if a
 *     paragraph is too long, or words if a sentence is too long.
 *  2. Pack pieces into a chunk until the next one would not fit.
 *  3. Start the next chunk with the last few pieces of the previous
 *     one (the overlap), so an idea split across two chunks is still
 *     complete in at least one of them.
 *
 * Chunks never cross a page boundary, so every chunk has exactly one
 * page number for citations. Sizes are counted in characters.
 */
class TextChunker
{
    /** About 300 tokens: small enough to be precise, big enough for context. */
    public const DEFAULT_SIZE = 1200;

    public const DEFAULT_OVERLAP = 200;

    public function __construct(
        private int $size = self::DEFAULT_SIZE,
        private int $overlap = self::DEFAULT_OVERLAP,
    ) {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        if ($overlap < 0 || $overlap >= $size) {
            throw new InvalidArgumentException('Overlap must be 0 or more and smaller than the chunk size.');
        }
    }

    /**
     * @param  list<ExtractedPage>  $pages
     * @return list<Chunk> Positions start at 0 and continue across pages.
     */
    public function chunk(array $pages): array
    {
        $chunks = [];

        foreach ($pages as $page) {
            foreach ($this->split($page->text) as $content) {
                $chunks[] = new Chunk(count($chunks), $page->number, $content);
            }
        }

        return $chunks;
    }

    /**
     * Split one page of text into chunk strings.
     *
     * @return list<string>
     */
    public function split(string $text): array
    {
        $chunks = [];
        $current = []; // pieces in the chunk being built: [text, glue]
        $length = 0;

        foreach ($this->pieces($text) as [$piece, $glue]) {
            $added = mb_strlen($glue) + mb_strlen($piece);

            if ($current !== [] && $length + $added > $this->size) {
                $chunks[] = $this->join($current);

                $current = $this->overlapFrom($current);
                $length = $this->lengthOf($current);

                // Overlap plus this piece is too big: skip the overlap this time.
                if ($length + $added > $this->size) {
                    $current = [];
                    $length = 0;
                }
            }

            $length += $current === [] ? mb_strlen($piece) : $added;
            $current[] = [$piece, $glue];
        }

        if ($current !== []) {
            $chunks[] = $this->join($current);
        }

        // Drop chunks with no letters or digits (e.g. "---" or "* * *").
        return array_values(array_filter(
            $chunks,
            fn (string $chunk): bool => preg_match('/[\p{L}\p{N}]/u', $chunk) === 1,
        ));
    }

    /**
     * Cut text into pieces no longer than the chunk size.
     * Each piece remembers the "glue" that goes before it:
     * a blank line for a new paragraph, a space otherwise.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function pieces(string $text): array
    {
        $pieces = [];

        foreach (preg_split('/\n\s*\n/u', trim($text)) as $paragraph) {
            $glue = "\n\n";

            foreach ($this->fit(trim($paragraph)) as $piece) {
                $pieces[] = [$piece, $glue];
                $glue = ' ';
            }
        }

        return $pieces;
    }

    /**
     * @return list<string>
     */
    private function fit(string $paragraph): array
    {
        if ($paragraph === '') {
            return [];
        }

        if (mb_strlen($paragraph) <= $this->size) {
            return [$paragraph];
        }

        $pieces = [];

        // Split after . ! or ? followed by whitespace.
        foreach (preg_split('/(?<=[.!?])\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) as $sentence) {
            if (mb_strlen($sentence) <= $this->size) {
                $pieces[] = $sentence;

                continue;
            }

            foreach (preg_split('/\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                // A "word" longer than a whole chunk (e.g. a huge URL) is cut hard.
                array_push($pieces, ...mb_str_split($word, $this->size));
            }
        }

        return $pieces;
    }

    /**
     * The last pieces of a finished chunk that fit in the overlap.
     * Never all of them, so a chunk is never repeated whole.
     *
     * @param  list<array{0: string, 1: string}>  $pieces
     * @return list<array{0: string, 1: string}>
     */
    private function overlapFrom(array $pieces): array
    {
        $tail = [];
        $length = 0;

        for ($i = count($pieces) - 1; $i >= 1; $i--) {
            $length += mb_strlen($pieces[$i][0]) + ($tail === [] ? 0 : mb_strlen($tail[0][1]));

            if ($length > $this->overlap) {
                break;
            }

            array_unshift($tail, $pieces[$i]);
        }

        return $tail;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pieces
     */
    private function lengthOf(array $pieces): int
    {
        return mb_strlen($this->join($pieces));
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pieces
     */
    private function join(array $pieces): string
    {
        $text = '';

        foreach ($pieces as $index => [$piece, $glue]) {
            $text .= ($index === 0 ? '' : $glue).$piece;
        }

        return $text;
    }
}
