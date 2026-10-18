<?php

use App\Actions\Chat\StartConversation;
use App\Ai\Agents\KnowbaseAssistant;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    Embeddings::fake(fn () => [searchVector([0 => 1.0])]);

    $this->user = User::factory()->create();
    $this->team = Team::factory()->ownedBy($this->user)->create();
    searchableDocument($this->team, 'Refund Policy', [['Refunds within 30 days.', searchVector([0 => 1.0])]]);
});

test('a conversation starts with the first question and its answer', function () {
    KnowbaseAssistant::fake(['Within 30 days [1].']);

    $conversation = app(StartConversation::class)->handle($this->team, $this->user, "How long do I have\nto ask for a refund?");

    expect($conversation->team_id)->toBe($this->team->id)
        ->and($conversation->user_id)->toBe($this->user->id)
        ->and($conversation->title)->toBe('How long do I have to ask for a refund?')
        ->and($conversation->messages()->count())->toBe(2);
});

test('no empty conversation is left behind when the AI fails', function () {
    KnowbaseAssistant::fake(fn () => throw new RuntimeException('Gemini is busy.'));

    expect(fn () => app(StartConversation::class)->handle($this->team, $this->user, 'Refunds?'))
        ->toThrow(RuntimeException::class);

    expect(Conversation::count())->toBe(0);
});
