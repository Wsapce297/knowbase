<?php

use App\Enums\QuestionChannel;
use App\Enums\QuestionOutcome;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\QuestionLog;
use App\Models\Team;
use App\Models\User;
use App\Models\Widget;
use App\Widgets\VisitorToken;

/**
 * A saved source, as SendMessage stores it with an answer.
 *
 * @return array<string, mixed>
 */
function storedSource(bool $cited): array
{
    return [
        'number' => 1,
        'documentId' => 1,
        'documentTitle' => 'Refund Policy',
        'page' => 1,
        'content' => 'Refunds within 30 days.',
        'similarity' => 0.8,
        'cited' => $cited,
    ];
}

/**
 * A question and its answer, saved the way SendMessage saves them (but without a log).
 *
 * @param  list<array<string, mixed>>  $sources
 */
function savedQuestionAndAnswer(Conversation $conversation, string $question, array $sources, ?DateTimeInterface $answeredAt = null): ConversationMessage
{
    ConversationMessage::factory()->for($conversation)->create(['content' => $question]);

    return ConversationMessage::factory()->assistant($sources)->for($conversation)->create([
        'created_at' => $answeredAt ?? now(),
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->ownedBy($this->user)->create();
    $this->conversation = Conversation::factory()->for($this->team)->for($this->user)->create();
});

test('it creates logs for answers saved before logging existed', function () {
    $tenDaysAgo = now()->subDays(10)->startOfSecond();

    savedQuestionAndAnswer($this->conversation, 'How do refunds work?', [storedSource(cited: true)], $tenDaysAgo);
    savedQuestionAndAnswer($this->conversation, 'How long is shipping?', [storedSource(cited: false)]);
    savedQuestionAndAnswer($this->conversation, 'Capital of France?', []);

    $widgetConversation = Conversation::forceCreate([
        'team_id' => $this->team->id,
        'widget_id' => Widget::factory()->for($this->team)->create()->id,
        'visitor_token_hash' => VisitorToken::hash(VisitorToken::generate()),
        'title' => 'Opening hours',
    ]);
    savedQuestionAndAnswer($widgetConversation, 'Opening hours?', [storedSource(cited: true)]);

    $this->artisan('knowbase:backfill-question-logs')
        ->expectsOutputToContain('Created 4 question logs.')
        ->assertSuccessful();

    $log = fn (string $question): QuestionLog => QuestionLog::where('question', $question)->sole();

    expect($log('How do refunds work?')->outcome)->toBe(QuestionOutcome::Answered)
        ->and($log('How do refunds work?')->created_at->equalTo($tenDaysAgo))->toBeTrue()
        ->and($log('How do refunds work?')->normalized_question)->toBe('how do refunds work')
        ->and($log('How long is shipping?')->outcome)->toBe(QuestionOutcome::Uncited)
        ->and($log('Capital of France?')->outcome)->toBe(QuestionOutcome::NotFound)
        ->and($log('Opening hours?')->channel)->toBe(QuestionChannel::Widget)
        ->and(QuestionLog::where('team_id', $this->team->id)->count())->toBe(4);
});

test('running it again creates no duplicates', function () {
    savedQuestionAndAnswer($this->conversation, 'How do refunds work?', [storedSource(cited: true)]);

    $this->artisan('knowbase:backfill-question-logs')->assertSuccessful();

    $this->artisan('knowbase:backfill-question-logs')
        ->expectsOutputToContain('Created 0 question logs.')
        ->assertSuccessful();

    expect(QuestionLog::count())->toBe(1);
});
