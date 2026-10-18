<?php

namespace App\Ai;

/**
 * One passage found by DocumentSearch, with where it came from
 * (for citations) and how close it is to the question.
 */
final readonly class SearchResult
{
    public function __construct(
        public int $chunkId,
        public int $documentId,
        public string $documentTitle,
        public ?int $page,
        public string $content,
        public float $similarity, // 0.0 = unrelated, 1.0 = same meaning
    ) {}
}
