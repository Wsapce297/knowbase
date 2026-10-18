<?php

use App\Ai\Agents\KnowbaseAssistant;
use App\Ai\AskKnowbase;
use App\Models\Team;
use App\Models\User;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    Embeddings::fake(fn () => [searchVector([0 => 1.0])]);

    $this->team = Team::factory()->ownedBy(User::factory()->create())->create(['name' => 'Acme Support']);
});

test('the answer comes with its numbered sources', function () {
    searchableDocument($this->team, 'Refund Policy', [
        ['Refunds are possible within 30 days.', searchVector([0 => 1.0])],
    ]);
    KnowbaseAssistant::fake(['You can get a refund within 30 days [1].']);

    $answer = app(AskKnowbase::class)->ask($this->team, 'How do refunds work?');

    expect($answer->text)->toBe('You can get a refund within 30 days [1].')
        ->and($answer->sources)->toHaveCount(1)
        ->and($answer->sources[0]->documentTitle)->toBe('Refund Policy')
        ->and($answer->citedNumbers())->toBe([1])
        ->and($answer->provider)->not->toBeNull();
});

test('the model receives numbered sources and the question', function () {
    searchableDocument($this->team, 'Refund Policy', [
        ['Refunds are possible within 30 days.', searchVector([0 => 1.0])],
        ['Refunds go back to the original card.', searchVector([0 => 0.9, 1 => 0.436])],
    ]);
    KnowbaseAssistant::fake();

    app(AskKnowbase::class)->ask($this->team, 'How do refunds work?');

    KnowbaseAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains("[1] Refund Policy, page 1\nRefunds are possible within 30 days.")
        && $prompt->contains("[2] Refund Policy, page 2\nRefunds go back to the original card.")
        && $prompt->contains('Question: How do refunds work?'));
});

test('the assistant knows which team it works for', function () {
    searchableDocument($this->team, 'Refund Policy', [['Refunds within 30 days.', searchVector([0 => 1.0])]]);
    KnowbaseAssistant::fake();

    app(AskKnowbase::class)->ask($this->team, 'Refunds?');

    KnowbaseAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent instanceof KnowbaseAssistant
        && str_contains($prompt->agent->instructions(), 'support assistant for "Acme Support"'));
});

test('no model is called when no document matches', function () {
    KnowbaseAssistant::fake();

    $answer = app(AskKnowbase::class)->ask($this->team, 'What is the capital of France?');

    expect($answer->text)->toBe(AskKnowbase::NOTHING_FOUND)
        ->and($answer->sources)->toBe([])
        ->and($answer->provider)->toBeNull();

    KnowbaseAssistant::assertNeverPrompted();
});

test('the assistant tries the configured providers in order', function () {
    config(['knowbase.chat.providers' => ['gemini', 'ollama']]);

    expect((new KnowbaseAssistant)->provider())->toBe(['gemini', 'ollama']);
});
