<?php

use App\Enums\TeamRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->ownedBy($this->user)->create();
    $this->user->switchTeam($this->team);
});

test('the chat page lists your own conversations in this team, newest first', function () {
    $older = Conversation::factory()->for($this->team)->for($this->user)->create(['title' => 'Older', 'updated_at' => now()->subDay()]);
    $newer = Conversation::factory()->for($this->team)->for($this->user)->create(['title' => 'Newer']);

    $teammate = User::factory()->create();
    $this->team->members()->attach($teammate, ['role' => TeamRole::Member->value]);
    Conversation::factory()->for($this->team)->for($teammate)->create(['title' => 'Not mine']);
    Conversation::factory()->for($this->user)->create(['title' => 'Other team']);

    $this->actingAs($this->user)
        ->get(route('chat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('chat/Index')
            ->has('conversations', 2)
            ->where('conversations.0.id', $newer->id)
            ->where('conversations.1.id', $older->id)
            ->where('conversation', null)
            ->where('messages', [])
            ->where('maxQuestionLength', 2000)
        );
});

test('a conversation shows its messages with sources', function () {
    $conversation = Conversation::factory()->for($this->team)->for($this->user)->create(['title' => 'Refunds']);
    ConversationMessage::factory()->for($conversation)->create(['content' => 'How do refunds work?']);
    ConversationMessage::factory()->assistant([
        ['number' => 1, 'documentTitle' => 'Refund Policy', 'cited' => true],
    ])->for($conversation)->create(['content' => 'Within 30 days [1].']);

    $this->actingAs($this->user)
        ->get(route('chat.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('chat/Index')
            ->where('conversation.id', $conversation->id)
            ->where('conversation.title', 'Refunds')
            ->has('messages', 2)
            ->where('messages.0.role', 'user')
            ->where('messages.1.content', 'Within 30 days [1].')
            ->where('messages.1.sources.0.documentTitle', 'Refund Policy')
        );
});

test('you cannot open someone else\'s conversation', function () {
    $teammate = User::factory()->create();
    $this->team->members()->attach($teammate, ['role' => TeamRole::Member->value]);
    $theirs = Conversation::factory()->for($this->team)->for($teammate)->create();

    $this->actingAs($this->user)->get(route('chat.show', $theirs))->assertForbidden();
});

test('you can delete your own conversation', function () {
    $conversation = Conversation::factory()->for($this->team)->for($this->user)->create();
    ConversationMessage::factory()->count(2)->for($conversation)->create();

    $this->actingAs($this->user)
        ->delete(route('chat.destroy', $conversation))
        ->assertRedirect(route('chat.index'));

    expect(Conversation::count())->toBe(0)
        ->and(ConversationMessage::count())->toBe(0);
});

test('you cannot delete someone else\'s conversation', function () {
    $teammate = User::factory()->create();
    $this->team->members()->attach($teammate, ['role' => TeamRole::Member->value]);
    $theirs = Conversation::factory()->for($this->team)->for($teammate)->create();

    $this->actingAs($this->user)->delete(route('chat.destroy', $theirs))->assertForbidden();

    expect(Conversation::count())->toBe(1);
});

test('guests are sent to the login page', function () {
    $this->get(route('chat.index'))->assertRedirect(route('login'));
});
