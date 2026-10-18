<?php

use App\Ai\DocumentSearch;
use App\Ai\Embedder;
use App\Ai\SearchResult;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Team;
use App\Models\User;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

/**
 * A 768-number vector that is zero except for the given positions.
 * Example: searchVector([0 => 1.0]) points along the "first" direction.
 *
 * @param  array<int, float>  $values
 * @return list<float>
 */
function searchVector(array $values): array
{
    $vector = array_fill(0, Embedder::DIMENSIONS, 0.0);

    foreach ($values as $index => $value) {
        $vector[$index] = $value;
    }

    return $vector;
}

/**
 * A ready document in $team with one chunk per [content, embedding] pair.
 *
 * @param  list<array{0: string, 1: list<float>}>  $chunks
 */
function searchableDocument(Team $team, string $title, array $chunks): Document
{
    $document = Document::factory()->ready()->for($team)->create(['title' => $title]);

    foreach ($chunks as $position => [$content, $embedding]) {
        DocumentChunk::factory()->for($document)->create([
            'position' => $position,
            'page' => $position + 1,
            'content' => $content,
            'embedding' => $embedding,
        ]);
    }

    return $document;
}

beforeEach(function () {
    // Every question becomes the "first direction" vector.
    Embeddings::fake(fn (EmbeddingsPrompt $prompt) => [searchVector([0 => 1.0])]);

    $this->team = Team::factory()->ownedBy(User::factory()->create())->create();
});

test('the closest passages come first with their source', function () {
    $document = searchableDocument($this->team, 'Refund Policy', [
        ['Somewhat related', searchVector([0 => 0.8, 1 => 0.6])], // similarity 0.8
        ['Exact match', searchVector([0 => 1.0])],                // similarity 1.0
    ]);

    $results = app(DocumentSearch::class)->search($this->team, 'How do refunds work?');

    expect($results)->toHaveCount(2)
        ->and($results[0])->toBeInstanceOf(SearchResult::class)
        ->and($results[0]->content)->toBe('Exact match')
        ->and($results[0]->similarity)->toBe(1.0)
        ->and($results[0]->documentId)->toBe($document->id)
        ->and($results[0]->documentTitle)->toBe('Refund Policy')
        ->and($results[0]->page)->toBe(2)
        ->and($results[1]->content)->toBe('Somewhat related')
        ->and($results[1]->similarity)->toBe(0.8);
});

test('unrelated passages are left out', function () {
    searchableDocument($this->team, 'Handbook', [
        ['Related', searchVector([0 => 0.6, 1 => 0.8])],   // similarity 0.6
        ['Unrelated', searchVector([1 => 1.0])],           // similarity 0.0
        ['Barely related', searchVector([0 => 0.3, 1 => 0.954])], // similarity 0.3
    ]);

    $results = app(DocumentSearch::class)->search($this->team, 'Anything?');

    expect(array_map(fn (SearchResult $r) => $r->content, $results))->toBe(['Related']);
});

test('only the given team is searched', function () {
    $otherTeam = Team::factory()->ownedBy(User::factory()->create())->create();
    searchableDocument($otherTeam, 'Secret', [['Other team data', searchVector([0 => 1.0])]]);
    searchableDocument($this->team, 'Ours', [['Our data', searchVector([0 => 1.0])]]);

    $results = app(DocumentSearch::class)->search($this->team, 'Show me everything');

    expect($results)->toHaveCount(1)
        ->and($results[0]->content)->toBe('Our data');
});

test('the number of passages is limited', function () {
    searchableDocument($this->team, 'Long Guide', array_map(
        fn (int $i): array => ["Passage {$i}", searchVector([0 => 1.0])],
        range(1, 8),
    ));

    expect(app(DocumentSearch::class)->search($this->team, 'Question'))->toHaveCount(DocumentSearch::LIMIT)
        ->and(app(DocumentSearch::class)->search($this->team, 'Question', limit: 2))->toHaveCount(2);
});

test('the question is embedded as a search query', function () {
    app(DocumentSearch::class)->search($this->team, 'How do refunds work?');

    Embeddings::assertGenerated(
        fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('search_query: How do refunds work?'),
    );
});

test('a blank question finds nothing without calling the model', function () {
    expect(app(DocumentSearch::class)->search($this->team, '   '))->toBe([]);

    Embeddings::assertNothingGenerated();
});
