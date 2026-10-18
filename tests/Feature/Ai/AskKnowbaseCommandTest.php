<?php

use App\Ai\Agents\KnowbaseAssistant;
use App\Models\Team;
use App\Models\User;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    Embeddings::fake(fn () => [searchVector([0 => 1.0])]);

    $this->team = Team::factory()->ownedBy(User::factory()->create())->create(['name' => 'Acme Support']);
    searchableDocument($this->team, 'Refund Policy', [['Refunds within 30 days.', searchVector([0 => 1.0])]]);
});

test('the command prints the answer and its sources', function () {
    KnowbaseAssistant::fake(['Refunds are possible within 30 days [1].']);

    $this->artisan('knowbase:ask', ['team' => 'Acme Support', 'question' => 'Refunds?'])
        ->expectsOutputToContain('Refunds are possible within 30 days [1].')
        ->expectsOutputToContain('[1] Refund Policy, page 1')
        ->assertSuccessful();
});

test('the command explains what went wrong', function () {
    KnowbaseAssistant::fake(fn () => throw new RuntimeException('Gemini is busy.'));

    $this->artisan('knowbase:ask', ['team' => 'Acme Support', 'question' => 'Refunds?'])
        ->expectsOutputToContain('Gemini is busy.')
        ->assertFailed();
});
