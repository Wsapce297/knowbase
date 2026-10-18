<?php

use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Team;
use App\Models\User;

test('a conversation belongs to a team and a user and lists messages oldest first', function () {
    $user = User::factory()->create();
    $team = Team::factory()->ownedBy($user)->create();
    $conversation = Conversation::factory()->for($team)->for($user)->create();

    ConversationMessage::factory()->for($conversation)->create(['content' => 'Question?']);
    ConversationMessage::factory()->assistant()->for($conversation)->create(['content' => 'Answer.']);

    expect($conversation->team->is($team))->toBeTrue()
        ->and($conversation->user->is($user))->toBeTrue()
        ->and($conversation->messages()->pluck('content')->all())->toBe(['Question?', 'Answer.']);
});

test('roles and sources are cast to useful types', function () {
    $message = ConversationMessage::factory()->assistant([
        ['number' => 1, 'documentTitle' => 'Refund Policy', 'cited' => true],
    ])->create();

    $message->refresh();

    expect($message->role)->toBe(MessageRole::Assistant)
        ->and($message->sources)->toBe([['number' => 1, 'documentTitle' => 'Refund Policy', 'cited' => true]]);
});

test('deleting a team deletes its conversations and messages', function () {
    $user = User::factory()->create();
    $team = Team::factory()->ownedBy($user)->create();
    $conversation = Conversation::factory()->for($team)->for($user)->create();
    ConversationMessage::factory()->count(2)->for($conversation)->create();

    $team->delete();

    expect(Conversation::count())->toBe(0)
        ->and(ConversationMessage::count())->toBe(0);
});
