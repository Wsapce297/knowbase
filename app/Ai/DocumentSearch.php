<?php

namespace App\Ai;

use App\Models\DocumentChunk;
use App\Models\Team;
use Illuminate\Support\Str;

/**
 * Finds the passages in a team's documents that best match a question.
 *
 * 1. Turn the question into 768 numbers (Embedder::query adds "search_query: ").
 * 2. Ask PostgreSQL for the team's chunks whose embeddings point in the most
 *    similar direction (cosine distance, using the HNSW index).
 * 3. Keep only reasonably close ones, best first, without duplicates.
 */
class DocumentSearch
{
    /** How many passages to send to the chat model. */
    public const LIMIT = 5;

    /** Passages less similar than this are treated as unrelated. */
    public const MIN_SIMILARITY = 0.5;

    public function __construct(private Embedder $embedder) {}

    /**
     * @return list<SearchResult> Best match first. Empty when nothing is close enough.
     */
    public function search(
        Team $team,
        string $question,
        int $limit = self::LIMIT,
        float $minSimilarity = self::MIN_SIMILARITY,
    ): array {
        $question = trim($question);

        if ($question === '') {
            return [];
        }

        $vector = $this->embedder->query($question);

        $chunks = DocumentChunk::query()
            ->select(['id', 'document_id', 'page', 'content'])      // not the big embedding column
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->where('team_id', $team->id)                           // never another team's data
            ->whereVectorSimilarTo('embedding', $vector, minSimilarity: $minSimilarity) // also sorts closest first
            ->with('document:id,title')
            ->limit($limit * 3)                                     // extra, in case some are duplicates
            ->get();

        return $chunks
            // The same file uploaded twice gives identical passages; keep the first (closest).
            ->unique(fn (DocumentChunk $chunk): string => Str::lower(Str::squish($chunk->content)))
            ->take($limit)
            ->map(fn (DocumentChunk $chunk): SearchResult => new SearchResult(
                chunkId: $chunk->id,
                documentId: $chunk->document_id,
                documentTitle: $chunk->document->title,
                page: $chunk->page,
                content: $chunk->content,
                similarity: round(1 - (float) $chunk->distance, 4),
            ))
            ->values()
            ->all();
    }
}
