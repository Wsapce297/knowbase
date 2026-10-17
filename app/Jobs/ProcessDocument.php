<?php

namespace App\Jobs;

use App\Ai\Embedder;
use App\Documents\Chunking\Chunk;
use App\Documents\Chunking\TextChunker;
use App\Documents\Extraction\TextExtractionException;
use App\Documents\Extraction\TextExtractor;
use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Turns an uploaded document into searchable chunks, in the background:
 * read file -> extract text -> chunk -> embed -> save chunks.
 *
 * Status: pending -> processing -> ready (or failed, with a message).
 */
class ProcessDocument implements ShouldQueue
{
    use Queueable;

    /**
     * Shown to users when something unexpected breaks (details go to the log).
     */
    public const GENERIC_ERROR = 'Something went wrong while processing this document. Please try again later.';

    /**
     * Run once. A failed document can be uploaded again.
     */
    public int $tries = 1;

    /**
     * Seconds before the worker stops the job (embedding a big PDF on a laptop is slow).
     */
    public int $timeout = 600;

    /**
     * If the document is deleted before the job runs, quietly skip it.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Document $document) {}

    /**
     * Laravel fills in these three classes from the service container.
     */
    public function handle(TextExtractor $extractor, TextChunker $chunker, Embedder $embedder): void
    {
        $document = $this->document;

        $document->forceFill([
            'status' => DocumentStatus::Processing,
            'error' => null,
        ])->save();

        $contents = Storage::disk($document->disk)->get($document->path);

        if ($contents === null) {
            $this->markFailed('The uploaded file could not be found. Please upload it again.');

            return;
        }

        try {
            $pages = $extractor->extract($contents, pathinfo($document->original_name, PATHINFO_EXTENSION));
        } catch (TextExtractionException $e) {
            // An expected problem with the file itself: show its friendly message.
            $this->markFailed($e->getMessage());

            return;
        }

        $chunks = $chunker->chunk($pages);

        if ($chunks === []) {
            $this->markFailed(TextExtractionException::noText()->getMessage());

            return;
        }

        $embeddings = $embedder->documents(
            array_map(fn (Chunk $chunk): string => $chunk->content, $chunks),
        );

        // All chunks and the "ready" status are saved together, or not at all.
        DB::transaction(function () use ($document, $chunks, $embeddings): void {
            $document->chunks()->delete(); // makes re-processing safe

            foreach ($chunks as $index => $chunk) {
                $document->chunks()->forceCreate([
                    'team_id' => $document->team_id,
                    'position' => $chunk->position,
                    'page' => $chunk->page,
                    'content' => $chunk->content,
                    'embedding' => $embeddings[$index],
                ]);
            }

            $document->forceFill([
                'status' => DocumentStatus::Ready,
                'error' => null,
                'processed_at' => now(),
            ])->save();
        });
    }

    /**
     * Called by Laravel when handle() throws (e.g. Ollama isn't running).
     * The real exception is still logged and saved in the failed_jobs table.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed(self::GENERIC_ERROR);
    }

    private function markFailed(string $message): void
    {
        $this->document->forceFill([
            'status' => DocumentStatus::Failed,
            'error' => $message,
            'processed_at' => null,
        ])->save();
    }
}
