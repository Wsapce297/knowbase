<?php

namespace App\Analytics;

use App\Enums\QuestionChannel;
use App\Enums\QuestionOutcome;
use App\Models\QuestionLog;
use App\Models\Team;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;

/**
 * Statistics about one team's questions over one period, read from question_logs.
 *
 * Days are counted in the viewer's time zone: a question asked at 01:00 on the 16th
 * in Karachi belongs to the 16th, even though it was still 20:00 on the 15th in UTC.
 * The database stores UTC; PostgreSQL converts with
 * "created_at AT TIME ZONE 'UTC' AT TIME ZONE '<zone>'".
 *
 * Every query is scoped to the team, the period and (optionally) one channel.
 */
final class QuestionStats
{
    private string $timezone;

    private CarbonImmutable $now;

    /** The first moment of the period, in UTC (how the database stores times). */
    private CarbonImmutable $startUtc;

    public function __construct(
        private Team $team,
        private AnalyticsPeriod $period = AnalyticsPeriod::DEFAULT,
        string $timezone = 'UTC',
        private ?QuestionChannel $channel = null,
        ?CarbonImmutable $now = null,
    ) {
        // An unknown zone would make the SQL fail: fall back to UTC.
        $this->timezone = in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
        $this->now = $now ?? CarbonImmutable::now();
        $this->startUtc = $period->start($this->now, $this->timezone)->utc();
    }

    /**
     * Totals for the whole period.
     *
     * @return array{total: int, answered: int, notFound: int, uncited: int, unanswered: int, chat: int, widget: int, answeredRate: float|null}
     */
    public function summary(): array
    {
        $row = $this->logs()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where outcome = ?) as answered', [QuestionOutcome::Answered->value])
            ->selectRaw('count(*) filter (where outcome = ?) as not_found', [QuestionOutcome::NotFound->value])
            ->selectRaw('count(*) filter (where outcome = ?) as uncited', [QuestionOutcome::Uncited->value])
            ->selectRaw('count(*) filter (where channel = ?) as widget', [QuestionChannel::Widget->value])
            ->first();

        $total = (int) $row->total;
        $answered = (int) $row->answered;
        $widget = (int) $row->widget;

        return [
            'total' => $total,
            'answered' => $answered,
            'notFound' => (int) $row->not_found,
            'uncited' => (int) $row->uncited,
            'unanswered' => $total - $answered,
            'chat' => $total - $widget,
            'widget' => $widget,
            // null instead of 0% when nobody asked anything: "no data" is not "everything failed"
            'answeredRate' => $total > 0 ? round($answered / $total * 100, 1) : null,
        ];
    }

    /**
     * One entry per day of the period, oldest first, including days without questions.
     *
     * @return list<array{date: string, total: int, unanswered: int}>
     */
    public function daily(): array
    {
        $rows = $this->logs()
            ->selectRaw("((created_at AT TIME ZONE 'UTC') AT TIME ZONE ?)::date as day", [$this->timezone])
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where outcome <> ?) as unanswered', [QuestionOutcome::Answered->value])
            // "1" = the first selected column. Repeating the expression wouldn't work:
            // PostgreSQL can't tell that two "?" placeholders hold the same time zone.
            ->groupByRaw('1')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->day);

        $days = [];
        $day = $this->period->start($this->now, $this->timezone);

        for ($i = 0; $i < $this->period->days(); $i++, $day = $day->addDay()) {
            $date = $day->toDateString();
            $row = $rows->get($date);

            $days[] = [
                'date' => $date,
                'total' => (int) ($row->total ?? 0),
                'unanswered' => (int) ($row->unanswered ?? 0),
            ];
        }

        return $days;
    }

    /**
     * The most asked questions, grouped by normalized text ("How do refunds work?"
     * and "how do refunds work" are one). Shows the most recent wording.
     *
     * @return list<array{question: string, total: int, unanswered: int, lastAskedAt: string}>
     */
    public function topQuestions(int $limit = 10): array
    {
        return $this->groupedQuestions()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where outcome <> ?) as unanswered', [QuestionOutcome::Answered->value])
            ->orderByDesc('total')
            ->orderByDesc('last_asked_at')
            ->orderBy('normalized_question')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'question' => $row->question,
                'total' => (int) $row->total,
                'unanswered' => (int) $row->unanswered,
                'lastAskedAt' => $this->isoTime($row->last_asked_at),
            ])
            ->all();
    }

    /**
     * Questions that are still unanswered: their most recent ask got "nothing found"
     * or no citation. Once someone uploads a document and the question is answered,
     * it drops off this list by itself. Most frequent gaps first.
     *
     * @return list<array{question: string, timesUnanswered: int, lastAskedAt: string, outcome: string, outcomeLabel: string}>
     */
    public function unansweredQuestions(int $limit = 10): array
    {
        $latestOutcome = '(array_agg(outcome order by created_at desc, id desc))[1]';

        return $this->groupedQuestions()
            ->selectRaw("{$latestOutcome} as latest_outcome")
            ->selectRaw('count(*) filter (where outcome <> ?) as times_unanswered', [QuestionOutcome::Answered->value])
            ->havingRaw("{$latestOutcome} <> ?", [QuestionOutcome::Answered->value])
            ->orderByDesc('times_unanswered')
            ->orderByDesc('last_asked_at')
            ->orderBy('normalized_question')
            ->limit($limit)
            ->get()
            ->map(function (object $row): array {
                $outcome = QuestionOutcome::from($row->latest_outcome);

                return [
                    'question' => $row->question,
                    'timesUnanswered' => (int) $row->times_unanswered,
                    'lastAskedAt' => $this->isoTime($row->last_asked_at),
                    'outcome' => $outcome->value,
                    'outcomeLabel' => $outcome->label(),
                ];
            })
            ->all();
    }

    /**
     * Logs of this team, in this period (and channel), as a plain query.
     */
    private function logs(): Builder
    {
        return QuestionLog::query()
            ->where('team_id', $this->team->id)
            ->where('created_at', '>=', $this->startUtc)
            ->when($this->channel, fn ($query, QuestionChannel $channel) => $query->where('channel', $channel->value))
            ->toBase();
    }

    /**
     * Logs grouped by normalized question, with the latest wording and time.
     */
    private function groupedQuestions(): Builder
    {
        return $this->logs()
            ->where('normalized_question', '<>', '')
            ->select('normalized_question')
            ->selectRaw('(array_agg(question order by created_at desc, id desc))[1] as question')
            ->selectRaw('max(created_at) as last_asked_at')
            ->groupBy('normalized_question');
    }

    /**
     * A UTC time from the database as ISO 8601 (the page shows it in the viewer's zone).
     */
    private function isoTime(string $value): string
    {
        return CarbonImmutable::parse($value, 'UTC')->toIso8601String();
    }
}
