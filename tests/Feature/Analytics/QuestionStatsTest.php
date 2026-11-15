<?php

use App\Analytics\AnalyticsPeriod;
use App\Analytics\QuestionStats;
use App\Enums\QuestionChannel;
use App\Models\QuestionLog;
use App\Models\Team;
use Carbon\CarbonImmutable;

/**
 * A question log in $team at a UTC time like '2026-11-15 10:00'.
 */
function loggedQuestion(Team $team, string $atUtc, ?Closure $state = null): QuestionLog
{
    $factory = QuestionLog::factory()->for($team);

    if ($state !== null) {
        $factory = $state($factory);
    }

    return $factory->create(['created_at' => CarbonImmutable::parse($atUtc, 'UTC')]);
}

beforeEach(function () {
    // "Now" is fixed: Monday 16 November 2026, 12:00 UTC (17:00 in Karachi).
    $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00:00', 'UTC'));

    $this->team = Team::factory()->create();
});

test('the summary counts questions by outcome and channel', function () {
    loggedQuestion($this->team, '2026-11-16 09:00');
    loggedQuestion($this->team, '2026-11-15 09:00');
    loggedQuestion($this->team, '2026-11-14 09:00', fn ($f) => $f->fromWidget());
    loggedQuestion($this->team, '2026-11-13 09:00', fn ($f) => $f->notFound()->fromWidget());
    loggedQuestion($this->team, '2026-11-12 09:00', fn ($f) => $f->uncited());

    expect((new QuestionStats($this->team))->summary())->toBe([
        'total' => 5,
        'answered' => 3,
        'notFound' => 1,
        'uncited' => 1,
        'unanswered' => 2,
        'chat' => 3,
        'widget' => 2,
        'answeredRate' => 60.0,
    ]);
});

test('with no questions the answered rate is empty, not zero', function () {
    $summary = (new QuestionStats($this->team))->summary();

    expect($summary['total'])->toBe(0)
        ->and($summary['answeredRate'])->toBeNull();
});

test('only this team and this period are counted', function () {
    loggedQuestion($this->team, '2026-11-16 09:00');
    loggedQuestion($this->team, '2026-10-18 09:00'); // first day of the last 30 days
    loggedQuestion($this->team, '2026-10-17 23:59'); // one day too old
    loggedQuestion(Team::factory()->create(), '2026-11-16 09:00'); // another team

    expect((new QuestionStats($this->team, AnalyticsPeriod::Last30Days))->summary()['total'])->toBe(2)
        ->and((new QuestionStats($this->team, AnalyticsPeriod::Last7Days))->summary()['total'])->toBe(1)
        ->and((new QuestionStats($this->team, AnalyticsPeriod::Last90Days))->summary()['total'])->toBe(3);
});

test('the period starts at midnight in the viewer\'s time zone', function () {
    // The last 7 days in Karachi start on 10 Nov at 00:00 local = 9 Nov 19:00 UTC.
    loggedQuestion($this->team, '2026-11-09 19:30'); // 10 Nov 00:30 in Karachi
    loggedQuestion($this->team, '2026-11-09 18:30'); // still 9 Nov in Karachi

    expect((new QuestionStats($this->team, AnalyticsPeriod::Last7Days, 'Asia/Karachi'))->summary()['total'])->toBe(1)
        ->and((new QuestionStats($this->team, AnalyticsPeriod::Last7Days, 'UTC'))->summary()['total'])->toBe(0);
});

test('daily counts include every day of the period, in the viewer\'s time zone', function () {
    loggedQuestion($this->team, '2026-11-15 20:00'); // 16 Nov 01:00 in Karachi
    loggedQuestion($this->team, '2026-11-15 08:00', fn ($f) => $f->notFound());

    $karachi = (new QuestionStats($this->team, AnalyticsPeriod::Last7Days, 'Asia/Karachi'))->daily();
    $utc = (new QuestionStats($this->team, AnalyticsPeriod::Last7Days, 'UTC'))->daily();

    expect($karachi)->toHaveCount(7)
        ->and($karachi[0])->toBe(['date' => '2026-11-10', 'total' => 0, 'unanswered' => 0])
        ->and($karachi[5])->toBe(['date' => '2026-11-15', 'total' => 1, 'unanswered' => 1])
        ->and($karachi[6])->toBe(['date' => '2026-11-16', 'total' => 1, 'unanswered' => 0])
        ->and($utc[5])->toBe(['date' => '2026-11-15', 'total' => 2, 'unanswered' => 1])
        ->and($utc[6])->toBe(['date' => '2026-11-16', 'total' => 0, 'unanswered' => 0]);
});

test('statistics can be limited to one channel', function () {
    loggedQuestion($this->team, '2026-11-16 09:00');
    loggedQuestion($this->team, '2026-11-16 10:00', fn ($f) => $f->fromWidget());
    loggedQuestion($this->team, '2026-11-16 11:00', fn ($f) => $f->fromWidget()->notFound());

    $widget = new QuestionStats($this->team, channel: QuestionChannel::Widget);

    expect($widget->summary()['total'])->toBe(2)
        ->and($widget->summary()['chat'])->toBe(0)
        ->and(array_sum(array_column($widget->daily(), 'total')))->toBe(2)
        ->and($widget->unansweredQuestions())->toHaveCount(1);
});

test('top questions group the same question written differently', function () {
    loggedQuestion($this->team, '2026-11-14 09:00', fn ($f) => $f->asking('how do refunds work'));
    loggedQuestion($this->team, '2026-11-15 09:00', fn ($f) => $f->asking('How do REFUNDS work?')->notFound());
    loggedQuestion($this->team, '2026-11-16 09:00', fn ($f) => $f->asking('How do refunds work?'));
    loggedQuestion($this->team, '2026-11-16 10:00', fn ($f) => $f->asking('Where is my order?'));

    expect((new QuestionStats($this->team))->topQuestions())->toBe([
        [
            'question' => 'How do refunds work?', // the latest wording
            'total' => 3,
            'unanswered' => 1,
            'lastAskedAt' => '2026-11-16T09:00:00+00:00',
        ],
        [
            'question' => 'Where is my order?',
            'total' => 1,
            'unanswered' => 0,
            'lastAskedAt' => '2026-11-16T10:00:00+00:00',
        ],
    ]);
});

test('top questions are limited', function () {
    foreach (['One?', 'Two?', 'Three?'] as $question) {
        loggedQuestion($this->team, '2026-11-16 09:00', fn ($f) => $f->asking($question));
    }

    expect((new QuestionStats($this->team))->topQuestions(limit: 2))->toHaveCount(2);
});

test('unanswered questions only list gaps that are still open', function () {
    // Asked twice, never answered.
    loggedQuestion($this->team, '2026-11-14 09:00', fn ($f) => $f->asking('Do you ship to Mars?')->notFound());
    loggedQuestion($this->team, '2026-11-15 09:00', fn ($f) => $f->asking('Do you ship to Mars?')->uncited());

    // Unanswered once, then answered after a document was added: resolved.
    loggedQuestion($this->team, '2026-11-14 09:00', fn ($f) => $f->asking('What is the warranty?')->notFound());
    loggedQuestion($this->team, '2026-11-16 09:00', fn ($f) => $f->asking('What is the warranty?'));

    // Unanswered once.
    loggedQuestion($this->team, '2026-11-16 10:00', fn ($f) => $f->asking('Gift cards?')->notFound());

    expect((new QuestionStats($this->team))->unansweredQuestions())->toBe([
        [
            'question' => 'Do you ship to Mars?',
            'timesUnanswered' => 2,
            'lastAskedAt' => '2026-11-15T09:00:00+00:00',
            'outcome' => 'uncited',
            'outcomeLabel' => 'No source cited',
        ],
        [
            'question' => 'Gift cards?',
            'timesUnanswered' => 1,
            'lastAskedAt' => '2026-11-16T10:00:00+00:00',
            'outcome' => 'not_found',
            'outcomeLabel' => 'Nothing found',
        ],
    ]);
});

test('an unknown time zone falls back to UTC', function () {
    loggedQuestion($this->team, '2026-11-16 09:00');

    $stats = new QuestionStats($this->team, AnalyticsPeriod::Last7Days, 'Mars/Olympus_Mons');

    $days = $stats->daily();

    expect($stats->summary()['total'])->toBe(1)
        ->and(end($days)['date'])->toBe('2026-11-16');
});

test('unknown period values fall back to 30 days', function (?string $input, AnalyticsPeriod $expected) {
    expect(AnalyticsPeriod::fromInput($input))->toBe($expected);
})->with([
    '7 days' => ['7d', AnalyticsPeriod::Last7Days],
    '90 days' => ['90d', AnalyticsPeriod::Last90Days],
    'missing' => [null, AnalyticsPeriod::Last30Days],
    'unknown' => ['365d', AnalyticsPeriod::Last30Days],
]);
