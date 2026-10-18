<?php

use App\Ai\Embedder;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Team;
use App\Models\User;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    $direction = array_fill(0, Embedder::DIMENSIONS, 0.0);
    $direction[0] = 1.0;

    Embeddings::fake(fn () => [$direction]);

    $this->team = Team::factory()->ownedBy(User::factory()->create())->create(['name' => 'Acme Support']);
    $this->direction = $direction;
});

test('the command lists matching passages', function () {
    $document = Document::factory()->ready()->for($this->team)->create(['title' => 'Refund Policy']);
    DocumentChunk::factory()->for($document)->create([
        'position' => 0,
        'content' => 'Refunds are possible within 30 days.',
        'embedding' => $this->direction,
    ]);

    $this->artisan('knowbase:search', ['team' => 'Acme Support', 'question' => 'Refunds?'])
        ->expectsOutputToContain('1 passage(s) found in "Acme Support"')
        ->expectsOutputToContain('Refund Policy')
        ->assertSuccessful();
});

test('the command says when nothing matches', function () {
    $this->artisan('knowbase:search', ['team' => (string) $this->team->id, 'question' => 'Refunds?'])
        ->expectsOutputToContain('No passages were similar enough')
        ->assertSuccessful();
});

test('the command says when the team does not exist', function () {
    $this->artisan('knowbase:search', ['team' => 'Nope Inc', 'question' => 'Refunds?'])
        ->expectsOutputToContain('No team found for "Nope Inc"')
        ->assertFailed();
});
