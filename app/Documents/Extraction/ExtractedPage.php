<?php

namespace App\Documents\Extraction;

/**
 * One page of text read from a document.
 *
 * PDFs give one ExtractedPage per page (number 1, 2, 3...).
 * Markdown and plain-text files have no pages, so they give a
 * single ExtractedPage with number null.
 */
final readonly class ExtractedPage
{
    public function __construct(
        public ?int $number,
        public string $text,
    ) {}
}
