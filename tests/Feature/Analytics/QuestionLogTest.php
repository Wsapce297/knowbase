<?php

use App\Actions\Chat\SendMessage;
use App\Ai\Agents\KnowbaseAssistant;
use App\Enums\QuestionChannel;
use App\Enums\QuestionOutcome;
use App\Models\Conversation;
use App\Models\DocumentChunk;
use App\Models\QuestionLog;
use App\Models\Team;
use App\Models\User;
use App\Models\Widget;
use App\Widgets\VisitorToken;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\RateLimitedException;

beforeEach(function () {
    Embeddings::fake(fn () => [searchVector([0 => 1.0])]);

    $this->user = User::factory()->create();
    $this->team = Team::factory()->ownedBy($this->user)->create();
    $this->conversation = Conversation::factory()->for($this->team)->for($this->user)->create();

    searchableDocument($this->team, 'Refund Policy', [['Refunds within 30 days.', searchVector([0 => 1.0])]]);
});

test('an answered question is logged with its answer', function () {
    KnowbaseAssistant::fake(['Within 30 days [1].']);

    $reply = app(SendMessage::class)->handle($this->conversation, '  How do REFUNDS work? ');
    $log = QuestionLog::sole();

    expect($log->team_id)->toBe($this->team->id)
        ->and($log->conversation_message_id)->toBe($reply->id)
        ->and($log->channel)->toBe(QuestionChannel::Chat)
        ->and($log->question)->toBe('How do REFUNDS work?')
        ->and($log->normalized_question)->toBe('how do refunds work')
        ->and($log->outcome)->toBe(QuestionOutcome::Answered)
        ->and($log->sources_count)->toBe(1)
        ->and($log->created_at)->not->toBeNull();
});

test('streamed answers are logged too', function () {
    KnowbaseAssistant::fake(['Within 30 days [1].']);

    app(SendMessage::class)->stream($this->conversation, 'How do refunds work?', fn (string $text) => null);

    expect(QuestionLog::sole()->outcome)->toBe(QuestionOutcome::Answered);
});

test('a question with no matching documents is logged as not found', function () {
    DocumentChunk::query()->delete();
    KnowbaseAssistant::fake();

    app(SendMessage::class)->handle($this->conversation, 'What is the capital of France?');
    $log = QuestionLog::sole();

    expect($log->outcome)->toBe(QuestionOutcome::NotFound)
        ->and($log->sources_count)->toBe(0)
        ->and($log->provider)->toBeNull();
});

test('an answer that cites no source is logged as uncited', function () {
    KnowbaseAssistant::fake(['The documents do not say how long shipping takes.']);

    app(SendMessage::class)->handle($this->conversation, 'How long is shipping?');

    expect(QuestionLog::sole()->outcome)->toBe(QuestionOutcome::Uncited)
        ->and(QuestionLog::sole()->sources_count)->toBe(1);
});

test('widget questions are logged with the widget channel', function () {
    $widget = Widget::factory()->for($this->team)->create();
    $conversation = Conversation::forceCreate([
        'team_id' => $this->team->id,
        'widget_id' => $widget->id,
        'visitor_token_hash' => VisitorToken::hash(VisitorToken::generate()),
        'title' => 'Refunds',
    ]);
    KnowbaseAssistant::fake(['Within 30 days [1].']);

    app(SendMessage::class)->handle($conversation, 'How do refunds work?');

    expect(QuestionLog::sole()->channel)->toBe(QuestionChannel::Widget)
        ->and(QuestionLog::sole()->team_id)->toBe($this->team->id);
});

test('nothing is logged when answering fails', function () {
    KnowbaseAssistant::fake(fn () => throw RateLimitedException::forProvider('gemini'));

    expect(fn () => app(SendMessage::class)->handle($this->conversation, 'How do refunds work?'))
        ->toThrow(RateLimitedException::class);

    expect(QuestionLog::count())->toBe(0);
});

test('deleting a conversation keeps its question logs', function () {
    KnowbaseAssistant::fake(['Within 30 days [1].']);
    app(SendMessage::class)->handle($this->conversation, 'How do refunds work?');

    $this->conversation->delete();

    expect(QuestionLog::sole()->conversation_message_id)->toBeNull()
        ->and(QuestionLog::sole()->question)->toBe('How do refunds work?');
});

test('deleting a team deletes its question logs', function () {
    QuestionLog::factory()->for($this->team)->count(2)->create();
    QuestionLog::factory()->for(Team::factory())->create();

    $this->team->delete();

    expect(QuestionLog::count())->toBe(1);
});

test('question logs never store who asked', function () {
    expect(Schema::hasColumn('question_logs', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumn('question_logs', 'visitor_token_hash'))->toBeFalse();
});
