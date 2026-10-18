<?php

use App\Ai\Agents\KnowbaseAssistant;
use App\Ai\AskKnowbase;
use App\Enums\TeamRole;
use App\Models\Conversation;
use App\Models\DocumentChunk;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\RateLimitedException;

/**
 * Turn the streamed "data: {...}" lines into a list of arrays.
 *
 * @return list<array<string, mixed>>
 */
function chatEvents(TestResponse $response): array
{
    preg_match_all('/^data: (.+)$/m', $response->streamedContent(), $matches);

    return array_map(fn (string $json): array => json_decode($json, true), $matches[1]);
}

/**
 * All "text" pieces joined together.
 *
 * @param  list<array<string, mixed>>  $events
 */
function chatText(array $events): string
{
    return implode('', array_column(array_filter($events, fn (array $e) => $e['type'] === 'text'), 'text'));
}

beforeEach(function () {
    Embeddings::fake(fn () => [searchVector([0 => 1.0])]);

    $this->user = User::factory()->create();
    $this->team = Team::factory()->ownedBy($this->user)->create();
    $this->user->switchTeam($this->team);

    searchableDocument($this->team, 'Refund Policy', [['Refunds within 30 days.', searchVector([0 => 1.0])]]);
});

test('a first question starts a conversation and streams the answer', function () {
    KnowbaseAssistant::fake(['You can get a refund within 30 days [1].']);

    $response = $this->actingAs($this->user)
        ->post(route('chat.messages.store'), ['question' => 'How do refunds work?']);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $events = chatEvents($response);
    $done = end($events);
    $conversation = Conversation::sole();

    expect(chatText($events))->toBe('You can get a refund within 30 days [1].')
        ->and(count($events))->toBeGreaterThan(2) // really sent in pieces
        ->and($done['type'])->toBe('done')
        ->and($done['conversationId'])->toBe($conversation->id)
        ->and($done['message']['role'])->toBe('assistant')
        ->and($done['message']['content'])->toBe('You can get a refund within 30 days [1].')
        ->and($done['message']['sources'][0]['documentTitle'])->toBe('Refund Policy')
        ->and($done['message']['sources'][0]['cited'])->toBeTrue()
        ->and($conversation->title)->toBe('How do refunds work?')
        ->and($conversation->user_id)->toBe($this->user->id)
        ->and($conversation->messages()->count())->toBe(2);
});

test('a follow-up continues the same conversation', function () {
    $conversation = Conversation::factory()->for($this->team)->for($this->user)->create();
    KnowbaseAssistant::fake(['Within 30 days [1].']);

    $events = chatEvents($this->actingAs($this->user)->post(route('chat.messages.store'), [
        'question' => 'And how long do I have?',
        'conversation_id' => $conversation->id,
    ]));

    expect(end($events)['conversationId'])->toBe($conversation->id)
        ->and(Conversation::count())->toBe(1)
        ->and($conversation->messages()->count())->toBe(2);
});

test('nobody can continue someone else\'s conversation', function () {
    $teammate = User::factory()->create();
    $this->team->members()->attach($teammate, ['role' => TeamRole::Member->value]);
    $theirs = Conversation::factory()->for($this->team)->for($teammate)->create();
    KnowbaseAssistant::fake();

    $this->actingAs($this->user)
        ->post(route('chat.messages.store'), ['question' => 'Hi?', 'conversation_id' => $theirs->id])
        ->assertForbidden();

    KnowbaseAssistant::assertNeverPrompted();
});

test('an unknown conversation gives a 404', function () {
    $this->actingAs($this->user)
        ->post(route('chat.messages.store'), ['question' => 'Hi?', 'conversation_id' => 999999])
        ->assertNotFound();
});

test('the question is validated', function (array $data) {
    $this->actingAs($this->user)
        ->postJson(route('chat.messages.store'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');
})->with([
    'missing' => [[]],
    'too long' => [['question' => str_repeat('a', 2001)]],
]);

test('when the AI fails an error event is sent and no empty conversation remains', function () {
    KnowbaseAssistant::fake(fn () => throw RateLimitedException::forProvider('gemini'));

    $events = chatEvents($this->actingAs($this->user)
        ->post(route('chat.messages.store'), ['question' => 'How do refunds work?']));

    expect(end($events))->toBe([
        'type' => 'error',
        'message' => 'The AI service has reached its usage limit. Please wait a minute and try again.',
    ])->and(Conversation::count())->toBe(0);
});

test('when no document matches the fixed reply is sent without calling the model', function () {
    DocumentChunk::query()->delete();
    KnowbaseAssistant::fake();

    $events = chatEvents($this->actingAs($this->user)
        ->post(route('chat.messages.store'), ['question' => 'What is the capital of France?']));

    expect(chatText($events))->toBe(AskKnowbase::NOTHING_FOUND)
        ->and(end($events)['message']['sources'])->toBe([]);

    KnowbaseAssistant::assertNeverPrompted();
});

test('guests cannot chat', function () {
    $this->post(route('chat.messages.store'), ['question' => 'Hi?'])
        ->assertRedirect(route('login'));
});
