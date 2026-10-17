<?php

namespace App\Documents\Chunking;

/**
 * One piece of a document, ready to be embedded and saved as a
 * document_chunks row (position, page, content).
 */
final readonly class Chunk
{
    public function __construct(
        public int $position,
        public ?int $page,
        public string $content,
    ) {}
}
