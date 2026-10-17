<?php

use App\Ai\Embedder;
use App\Enums\DocumentStatus;
use App\Enums\TeamRole;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Laravel\Ai\Embeddings;

/**
 * A 768-number vector that points in exactly one direction (all zeros except one 1.0).
 * Handy for search tests: two such vectors are either identical or completely different.
 *
 * @return list<float>
 */
function directionVector(int $index): array
{
    $vector = array_fill(0, Embedder::DIMENSIONS, 0.0);
    $vector[$index] = 1.0;

    return $vector;
}

test('a document belongs to its team and uploader', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->ownedBy($owner)->create();

    $document = Document::factory()->for($team)->for($owner, 'uploader')->create();

    expect($team->documents)->toHaveCount(1)
        ->and($document->team->is($team))->toBeTrue()
        ->and($document->uploader->is($owner))->toBeTrue();
});

test('new documents start as pending', function () {
    expect((new Document)->status)->toBe(DocumentStatus::Pending);
});

test('the status knows its label and whether processing has finished', function () {
    expect(DocumentStatus::Pending->label())->toBe('Waiting')
        ->and(DocumentStatus::Pending->isFinished())->toBeFalse()
        ->and(DocumentStatus::Processing->isFinished())->toBeFalse()
        ->and(DocumentStatus::Ready->isFinished())->toBeTrue()
        ->and(DocumentStatus::Failed->isFinished())->toBeTrue();
});

test('a chunk stores and returns a 768-number embedding', function () {
    $document = Document::factory()->create();
    $vector = Embeddings::fakeEmbedding(Embedder::DIMENSIONS);

    $chunk = DocumentChunk::factory()->for($document)->create(['embedding' => $vector]);
    $saved = $chunk->fresh();

    expect($saved->embedding)->toHaveCount(768)
        ->and($saved->embedding[0])->toEqualWithDelta($vector[0], 0.0001)
        ->and($saved->team_id)->toBe($document->team_id);
});

test('the database rejects embeddings with the wrong size', function () {
    $document = Document::factory()->create();

    DocumentChunk::factory()->for($document)->create(['embedding' => [0.1, 0.2, 0.3]]);
})->throws(QueryException::class);

test('deleting a team removes its documents and chunks', function () {
    $team = Team::factory()->ownedBy(User::factory()->create())->create();
    $document = Document::factory()->for($team)->create();
    DocumentChunk::factory()->count(3)->for($document)->create();

    $team->delete();

    expect(Document::count())->toBe(0)
        ->and(DocumentChunk::count())->toBe(0);
});

test('deleting the uploader keeps the document', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->ownedBy($owner)->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $document = Document::factory()->for($team)->for($member, 'uploader')->create();

    $member->delete();

    expect($document->fresh())->not->toBeNull()
        ->and($document->fresh()->uploaded_by)->toBeNull();
});

test('vector search finds the closest chunk within the team', function () {
    $document = Document::factory()->create();
    $otherTeamDocument = Document::factory()->create();

    DocumentChunk::factory()->for($document)->create(['position' => 0, 'embedding' => directionVector(0)]);
    $second = DocumentChunk::factory()->for($document)->create(['position' => 1, 'embedding' => directionVector(1)]);
    DocumentChunk::factory()->for($document)->create(['position' => 2, 'embedding' => directionVector(2)]);

    // Same direction as $second, but it belongs to another team.
    DocumentChunk::factory()->for($otherTeamDocument)->create(['position' => 0, 'embedding' => directionVector(1)]);

    // A "question" that points mostly in direction 1, a little in 0 and 2.
    $question = directionVector(1);
    $question[0] = 0.1;
    $question[2] = 0.1;

    $results = DocumentChunk::query()
        ->where('team_id', $document->team_id)
        ->whereVectorSimilarTo('embedding', $question, minSimilarity: 0.5)
        ->get();

    expect($results->modelKeys())->toBe([$second->id]);
});
