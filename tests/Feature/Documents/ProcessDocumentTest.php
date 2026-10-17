<?php

use App\Ai\Embedder;
use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(function () {
    Storage::fake('local');

    // Every embedding is a random 768-number vector; Ollama is never called.
    Embeddings::fake();
});

/**
 * A pending document whose stored file holds $contents (null = no file).
 */
function documentWithFile(string $originalName, ?string $contents): Document
{
    $owner = User::factory()->create();
    $team = Team::factory()->ownedBy($owner)->create();

    $document = Document::factory()->for($team)->for($owner, 'uploader')->create([
        'original_name' => $originalName,
        'disk' => 'local',
        'path' => "teams/{$team->id}/documents/".Str::random(40).'.'.pathinfo($originalName, PATHINFO_EXTENSION),
    ]);

    if ($contents !== null) {
        Storage::disk('local')->put($document->path, $contents);
    }

    return $document;
}

test('a markdown document is chunked, embedded and marked ready', function () {
    $document = documentWithFile('refund-policy.md', "# Refunds\n\nCustomers can ask for a refund within 30 days.");

    ProcessDocument::dispatchSync($document);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->error)->toBeNull()
        ->and($document->processed_at)->not->toBeNull();

    $chunk = $document->chunks()->sole();

    expect($chunk->team_id)->toBe($document->team_id)
        ->and($chunk->position)->toBe(0)
        ->and($chunk->page)->toBeNull()
        ->and($chunk->content)->toContain('within 30 days')
        ->and($chunk->embedding)->toHaveCount(Embedder::DIMENSIONS);
});

test('pdf chunks keep their page numbers', function () {
    $document = documentWithFile('manual.pdf', file_get_contents(base_path('tests/Fixtures/documents/sample.pdf')));

    ProcessDocument::dispatchSync($document);

    expect($document->refresh()->status)->toBe(DocumentStatus::Ready)
        ->and($document->chunks()->orderBy('position')->pluck('page')->all())->toBe([1, 3]);
});

test('chunks are embedded as documents, not as questions', function () {
    $document = documentWithFile('hello.txt', 'Hello from Knowbase.');

    ProcessDocument::dispatchSync($document);

    Embeddings::assertGenerated(
        fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('search_document: Hello from Knowbase.'),
    );
});

test('a long document becomes several chunks in order', function () {
    $text = implode(' ', array_map(fn (int $i): string => "Sentence {$i} about our refund rules.", range(1, 150)));
    $document = documentWithFile('long.txt', $text);

    ProcessDocument::dispatchSync($document);

    $positions = $document->chunks()->orderBy('position')->pluck('position')->all();

    expect(count($positions))->toBeGreaterThan(2)
        ->and($positions)->toBe(range(0, count($positions) - 1));
});

test('a file without text fails with a helpful message', function () {
    $document = documentWithFile('empty.txt', '');

    ProcessDocument::dispatchSync($document);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toContain('No readable text was found')
        ->and($document->chunks()->count())->toBe(0);

    Embeddings::assertNothingGenerated();
});

test('a missing file fails with a helpful message', function () {
    $document = documentWithFile('gone.md', null);

    ProcessDocument::dispatchSync($document);

    expect($document->refresh()->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toContain('could not be found');
});

test('an unexpected error marks the document as failed', function () {
    Embeddings::fake(fn () => throw new RuntimeException('Ollama is down'));
    $document = documentWithFile('notes.md', 'Some notes.');

    expect(fn () => ProcessDocument::dispatchSync($document))
        ->toThrow(RuntimeException::class, 'Ollama is down');

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error)->toBe(ProcessDocument::GENERIC_ERROR)
        ->and($document->chunks()->count())->toBe(0);
});

test('processing again replaces the old chunks', function () {
    $document = documentWithFile('notes.md', 'Fresh content.');
    DocumentChunk::factory()->count(3)->for($document)->create();

    ProcessDocument::dispatchSync($document);

    expect($document->chunks()->pluck('content')->all())->toBe(['Fresh content.']);
});

test('a document deleted before processing is skipped', function () {
    $document = documentWithFile('notes.md', 'Some notes.');
    $document->delete();

    ProcessDocument::dispatchSync($document);

    Embeddings::assertNothingGenerated();
    expect(Document::count())->toBe(0);
});
