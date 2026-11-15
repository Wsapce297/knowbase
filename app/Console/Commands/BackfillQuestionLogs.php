<?php

namespace App\Console\Commands;

use App\Enums\MessageRole;
use App\Enums\QuestionOutcome;
use App\Models\ConversationMessage;
use App\Models\QuestionLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Creates analytics entries for answers saved before question logging existed.
 * Safe to run more than once: answers that already have an entry are skipped.
 */
class BackfillQuestionLogs extends Command
{
    protected $signature = 'knowbase:backfill-question-logs';

    protected $description = 'Create analytics question logs for answers saved before logging existed';

    public function handle(): int
    {
        $created = 0;

        ConversationMessage::query()
            ->where('role', MessageRole::Assistant)
            ->whereDoesntHave('questionLog')
            ->with('conversation')
            ->chunkById(200, function (Collection $answers) use (&$created): void {
                foreach ($answers as $answer) {
                    if ($this->backfill($answer)) {
                        $created++;
                    }
                }
            });

        $this->components->info("Created {$created} question ".($created === 1 ? 'log' : 'logs').'.');

        return self::SUCCESS;
    }

    private function backfill(ConversationMessage $answer): bool
    {
        // The question is the user message just before this answer.
        $question = ConversationMessage::query()
            ->where('conversation_id', $answer->conversation_id)
            ->where('role', MessageRole::User)
            ->where('id', '<', $answer->id)
            ->orderByDesc('id')
            ->value('content');

        if ($question === null) {
            return false;
        }

        $sources = $answer->sources ?? [];

        QuestionLog::forceCreate([
            ...QuestionLog::questionAttributes($answer->conversation, $question),
            'conversation_message_id' => $answer->id,
            'outcome' => QuestionOutcome::fromStoredSources($sources),
            'sources_count' => count($sources),
            'provider' => $answer->provider,
            'created_at' => $answer->created_at, // keep the original date for the charts
        ]);

        return true;
    }
}
