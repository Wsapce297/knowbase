<?php

use App\Actions\Chat\SendMessage;
use App\Ai\Agents\KnowbaseAssistant;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Team;
use App\Models\User;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Messages\MessageRole as AiMessageRole;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(function () {
    Embeddings::fake(fn () => [searchVector([0 => 1.0])]);

    $user = User::factory()->create();
    $team = Team::factory()->ownedBy($user)->create();
    searchableDocument($team, 'Refund Policy', [['Refunds within 30 days.', searchVector([0 => 1.0])]]);

    $this->conversation = Conversation::factory()->for($team)->for($user)->create();
});

test('the question and the answer are saved with the sources', function () {
    KnowbaseAssistant::fake(['You can get a refund within 30 days [1].']);

    $reply = app(SendMessage::class)->handle($this->conversation, '  How do refunds work?  ');

    expect($reply->role)->toBe(MessageRole::Assistant)
        ->and($reply->content)->toBe('You can get a refund within 30 days [1].')
        ->and($reply->provider)->not->toBeNull()
        ->and($reply->sources)->toHaveCount(1)
        ->and($reply->sources[0])->toMatchArray([
            'number' => 1,
            'documentTitle' => 'Refund Policy',
            'page' => 1,
            'content' => 'Refunds within 30 days.',
            'cited' => true,
        ]);

    $messages = $this->conversation->messages()->get();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->role)->toBe(MessageRole::User)
        ->and($messages[0]->content)->toBe('How do refunds work?')
        ->and($messages[1]->is($reply))->toBeTrue();
});

test('the conversation moves to the top of the list', function () {
    $this->conversation->forceFill(['updated_at' => now()->subDay()])->save();
    KnowbaseAssistant::fake();

    app(SendMessage::class)->handle($this->conversation, 'How do refunds work?');

    expect($this->conversation->fresh()->updated_at->isToday())->toBeTrue();
});

test('earlier messages are sent as history without citation markers', function () {
    ConversationMessage::factory()->for($this->conversation)->create(['content' => 'How do refunds work?']);
    ConversationMessage::factory()->assistant()->for($this->conversation)->create(['content' => 'Within 30 days [1].']);
    KnowbaseAssistant::fake();

    app(SendMessage::class)->handle($this->conversation, 'And how is the money returned?');

    KnowbaseAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => count($prompt->messages) === 2
        && $prompt->messages[0]->role === AiMessageRole::User
        && $prompt->messages[0]->content === 'How do refunds work?'
        && $prompt->messages[1]->role === AiMessageRole::Assistant
        && $prompt->messages[1]->content === 'Within 30 days.');

    // A follow-up is searched together with the previous question.
    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains("search_query: How do refunds work?\nAnd how is the money returned?"));
});

test('only the latest messages are sent as history', function () {
    foreach (range(1, 10) as $i) {
        ConversationMessage::factory()
            ->state($i % 2 === 1 ? [] : ['role' => MessageRole::Assistant])
            ->for($this->conversation)
            ->create(['content' => "Message {$i}"]);
    }
    KnowbaseAssistant::fake();

    app(SendMessage::class)->handle($this->conversation, 'Next question?');

    KnowbaseAssistant::assertPrompted(fn (AgentPrompt $prompt): bool => count($prompt->messages) === SendMessage::HISTORY_MESSAGES
        && $prompt->messages[0]->content === 'Message 5'
        && $prompt->messages[5]->content === 'Message 10');
});

test('nothing is saved when the AI fails', function () {
    KnowbaseAssistant::fake(fn () => throw new RuntimeException('Gemini is busy.'));

    expect(fn () => app(SendMessage::class)->handle($this->conversation, 'How do refunds work?'))
        ->toThrow(RuntimeException::class, 'Gemini is busy.');

    expect($this->conversation->messages()->count())->toBe(0);
});
