<?php

use App\Ai\Embedder;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

it('uses ollama for embeddings by default', function () {
    expect(config('ai.default_for_embeddings'))->toBe('ollama');
});

it('embeds a question as a search query with nomic-embed-text', function () {
    Embeddings::fake();

    $vector = app(Embedder::class)->query('What is the refund policy?');

    expect($vector)->toHaveCount(Embedder::DIMENSIONS);

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->inputs === ['search_query: What is the refund policy?']
        && $prompt->provider->name() === 'ollama'
        && $prompt->model === 'nomic-embed-text'
        && $prompt->dimensions === 768);
});

it('embeds document chunks in batches and keeps their order', function () {
    $batchSizes = [];

    Embeddings::fake(function (EmbeddingsPrompt $prompt) use (&$batchSizes) {
        $batchSizes[] = count($prompt);

        return null; // null = let the fake create random embeddings
    });

    $texts = array_map(fn (int $i): string => "Chunk number {$i}", range(1, 70));

    $vectors = app(Embedder::class)->documents($texts);

    expect($vectors)->toHaveCount(70)
        ->and($vectors[0])->toHaveCount(Embedder::DIMENSIONS)
        ->and($batchSizes)->toBe([32, 32, 6]);

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('search_document: Chunk number 1'));
});

it('does not call the provider when there is nothing to embed', function () {
    Embeddings::fake();

    expect(app(Embedder::class)->documents([]))->toBe([]);

    Embeddings::assertNothingGenerated();
});

it('rejects embeddings with the wrong size', function () {
    // One fake response containing one embedding with only 3 numbers.
    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    app(Embedder::class)->query('Hello');
})->throws(RuntimeException::class, 'Expected 768-dimension embeddings but received 3');
