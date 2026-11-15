<?php

use App\Ai\Agents\KnowbaseAssistant;
use App\Ai\AskKnowbase;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Team;
use App\Models\Widget;
use App\Widgets\VisitorToken;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Embeddings;

const WIDGET_TEST_ANSWER = 'Customers can ask for a refund within 30 days [1].';

/**
 * The data: {...} events of a streamed widget answer, decoded.
 *
 * @return list<array<string, mixed>>
 */
function widgetEvents(TestResponse $response): array
{
    $events = [];

    foreach (explode("\n\n", trim($response->streamedContent())) as $block) {
        if (str_starts_with($block, 'data: ')) {
            $events[] = json_decode(substr($block, 6), true);
        }
    }

    return $events;
}

function widgetMessageUrl(Widget $widget): string
{
    return route('widget.messages.store', ['key' => $widget->public_key]);
}

/**
 * An existing widget conversation with one question and answer, continued with $token.
 */
function existingWidgetConversation(Widget $widget, string $token): Conversation
{
    $conversation = Conversation::forceCreate([
        'team_id' => $widget->team_id,
        'widget_id' => $widget->id,
        'visitor_token_hash' => VisitorToken::hash($token),
        'title' => 'How do refunds work?',
    ]);

    $conversation->messages()->create(['role' => MessageRole::User, 'content' => 'How do refunds work?']);
    $conversation->messages()->create(['role' => MessageRole::Assistant, 'content' => 'Within 30 days [1].']);

    return $conversation;
}

beforeEach(function () {
    $this->team = Team::factory()->create(['name' => 'Acme Support']);
    $this->widget = Widget::factory()->for($this->team)->create();

    searchableDocument($this->team, 'Refund Policy', [
        ['Customers can ask for a refund within 30 days of purchase.', searchVector([0 => 1.0])],
    ]);

    Embeddings::fake(fn () => [searchVector([0 => 1.0])]);
});

test('a first question streams the answer and starts an anonymous conversation', function () {
    KnowbaseAssistant::fake([WIDGET_TEST_ANSWER]);

    $events = widgetEvents(
        $this->postJson(widgetMessageUrl($this->widget), ['question' => 'How do refunds work?'])->assertOk()
    );

    $done = end($events);

    expect(collect($events)->where('type', 'text'))->not->toBeEmpty()
        ->and($done['type'])->toBe('done')
        ->and($done['message']['content'])->toBe(WIDGET_TEST_ANSWER)
        ->and(VisitorToken::isWellFormed($done['visitorToken']))->toBeTrue()
        ->and($done)->not->toHaveKey('conversationId');

    $conversation = Conversation::sole();

    expect($conversation->user_id)->toBeNull()
        ->and($conversation->widget_id)->toBe($this->widget->id)
        ->and($conversation->team_id)->toBe($this->team->id)
        ->and($conversation->title)->toBe('How do refunds work?')
        ->and($conversation->visitor_token_hash)->toBe(VisitorToken::hash($done['visitorToken']))
        ->and($conversation->messages()->count())->toBe(2);
});

test('a follow-up with the visitor token continues the same conversation with its history', function () {
    $token = VisitorToken::generate();
    $conversation = existingWidgetConversation($this->widget, $token);

    KnowbaseAssistant::fake([WIDGET_TEST_ANSWER]);

    $events = widgetEvents($this->postJson(widgetMessageUrl($this->widget), [
        'question' => 'And how long does it take?',
        'visitor_token' => $token,
    ])->assertOk());

    expect(end($events)['type'])->toBe('done')
        ->and(end($events)['visitorToken'])->toBeNull() // the visitor already has it
        ->and(Conversation::count())->toBe(1)
        ->and($conversation->messages()->count())->toBe(4);

    KnowbaseAssistant::assertPrompted(fn ($prompt) => count($prompt->messages) === 2);
});

test('the answer only shows cited sources, without the passage text', function () {
    KnowbaseAssistant::fake([WIDGET_TEST_ANSWER]);

    $events = widgetEvents($this->postJson(widgetMessageUrl($this->widget), ['question' => 'How do refunds work?']));

    expect(end($events)['message']['sources'])->toBe([
        ['number' => 1, 'documentTitle' => 'Refund Policy', 'page' => 1],
    ]);
});

test('a question with no matching documents is answered without calling the model', function () {
    Embeddings::fake(fn () => [searchVector([1 => 1.0])]); // points away from every chunk
    KnowbaseAssistant::fake();

    $events = widgetEvents($this->postJson(widgetMessageUrl($this->widget), ['question' => 'What is the weather?']));

    expect(end($events)['message']['content'])->toBe(AskKnowbase::NOTHING_FOUND)
        ->and(end($events)['message']['sources'])->toBe([]);

    KnowbaseAssistant::assertNeverPrompted();
});

test('unknown and disabled widget keys return 404', function () {
    $disabled = Widget::factory()->for(Team::factory())->disabled()->create();

    $this->postJson(route('widget.messages.store', ['key' => 'kbw_'.str_repeat('a', 32)]), ['question' => 'Hi'])
        ->assertNotFound();

    $this->postJson(widgetMessageUrl($disabled), ['question' => 'Hi'])
        ->assertNotFound();

    expect(Conversation::count())->toBe(0);
});

test('an unknown visitor token returns 404', function () {
    $this->postJson(widgetMessageUrl($this->widget), [
        'question' => 'Hi',
        'visitor_token' => VisitorToken::generate(),
    ])->assertNotFound();

    expect(Conversation::count())->toBe(0);
});

test('a visitor token cannot continue another widget\'s conversation', function () {
    $token = VisitorToken::generate();
    existingWidgetConversation(Widget::factory()->for(Team::factory())->create(), $token);

    $this->postJson(widgetMessageUrl($this->widget), [
        'question' => 'Hi',
        'visitor_token' => $token,
    ])->assertNotFound();
});

test('a malformed visitor token is rejected', function () {
    $this->postJson(widgetMessageUrl($this->widget), [
        'question' => 'Hi',
        'visitor_token' => 'not-a-token',
    ])->assertUnprocessable()->assertJsonValidationErrors('visitor_token');
});

test('the question is validated', function (mixed $question) {
    $this->postJson(widgetMessageUrl($this->widget), ['question' => $question])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');
})->with([
    'missing' => [''],
    'too long' => [str_repeat('a', 1001)],
]);

test('validation errors are JSON even without an Accept header', function () {
    // The embed routes have no session, so a redirect with errors would crash.
    $this->post(widgetMessageUrl($this->widget), ['question' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');
});

test('a failed answer sends an error event and leaves no conversation behind', function () {
    KnowbaseAssistant::fake(fn () => throw new RuntimeException('Gemini is down'));

    $events = widgetEvents($this->postJson(widgetMessageUrl($this->widget), ['question' => 'How do refunds work?']));

    expect(end($events))->toBe([
        'type' => 'error',
        'message' => 'Something went wrong while answering. Please try again.',
    ])->and(Conversation::count())->toBe(0);
});

test('requests from the Knowbase iframe origin are accepted', function () {
    KnowbaseAssistant::fake([WIDGET_TEST_ANSWER]);

    $response = $this->withHeader('Origin', rtrim(config('app.url'), '/'))
        ->postJson(widgetMessageUrl($this->widget), ['question' => 'How do refunds work?'])
        ->assertOk();

    $events = widgetEvents($response);

    expect(end($events)['type'])->toBe('done');
});

test('requests from other websites are refused', function () {
    $this->withHeader('Origin', 'https://example.com') // even an allowed website must go through the iframe
        ->postJson(widgetMessageUrl($this->widget), ['question' => 'How do refunds work?'])
        ->assertForbidden();

    expect(Conversation::count())->toBe(0);
});

test('one visitor is rate limited per widget', function () {
    config(['knowbase.widget.rate_limits.per_visitor' => 2]);

    // Empty questions fail validation, but still count: throttling runs first.
    $this->postJson(widgetMessageUrl($this->widget), ['question' => ''])->assertUnprocessable();
    $this->postJson(widgetMessageUrl($this->widget), ['question' => ''])->assertUnprocessable();

    $this->postJson(widgetMessageUrl($this->widget), ['question' => ''])
        ->assertTooManyRequests()
        ->assertJson(['message' => 'Too many questions. Please wait a minute and try again.']);

    // Another visitor is not affected.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson(widgetMessageUrl($this->widget), ['question' => ''])
        ->assertUnprocessable();
});

test('all visitors together are rate limited per widget', function () {
    config(['knowbase.widget.rate_limits.per_widget' => 2]);

    foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(widgetMessageUrl($this->widget), ['question' => ''])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
        ->postJson(widgetMessageUrl($this->widget), ['question' => ''])
        ->assertTooManyRequests();

    // Other widgets keep working.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
        ->postJson(widgetMessageUrl(Widget::factory()->for(Team::factory())->create()), ['question' => ''])
        ->assertUnprocessable();
});
