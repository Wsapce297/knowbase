<?php

use App\Models\Conversation;
use App\Models\Team;
use App\Models\User;
use App\Models\Widget;
use App\Widgets\VisitorToken;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A team owner (with the team as current team) and an anonymous widget conversation in that team.
 *
 * @return array{User, Team, Conversation}
 */
function ownerWithWidgetConversation(): array
{
    $user = User::factory()->create();
    $team = Team::factory()->ownedBy($user)->create();
    $user->switchTeam($team);

    $widget = Widget::factory()->for($team)->create();

    $conversation = Conversation::forceCreate([
        'team_id' => $team->id,
        'widget_id' => $widget->id,
        'visitor_token_hash' => VisitorToken::hash(VisitorToken::generate()),
        'title' => 'A visitor question',
    ]);

    return [$user, $team, $conversation];
}

test('widget conversations do not appear in the private chat list', function () {
    [$user, $team] = ownerWithWidgetConversation();

    Conversation::create(['team_id' => $team->id, 'user_id' => $user->id, 'title' => 'My own question']);

    $this->actingAs($user)
        ->get(route('chat.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('chat/Index')
            ->has('conversations', 1)
        );
});

test('team members cannot open a widget conversation in the chat page', function () {
    [$user, , $conversation] = ownerWithWidgetConversation();

    $this->actingAs($user)
        ->get(route('chat.show', $conversation))
        ->assertForbidden();
});

test('team members cannot continue a widget conversation from the chat page', function () {
    [$user, , $conversation] = ownerWithWidgetConversation();

    $this->actingAs($user)
        ->postJson(route('chat.messages.store'), [
            'question' => 'Hello?',
            'conversation_id' => $conversation->id,
        ])
        ->assertForbidden();
});

test('the database refuses a conversation that has neither a user nor a widget', function () {
    $team = Team::factory()->create();

    // Last in the test: a failed statement aborts PostgreSQL's test transaction.
    expect(fn () => Conversation::forceCreate(['team_id' => $team->id, 'title' => 'Nobody']))
        ->toThrow(QueryException::class);
});
