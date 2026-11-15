<?php

namespace App\Models;

use App\Ai\Answer;
use App\Analytics\QuestionNormalizer;
use App\Enums\QuestionChannel;
use App\Enums\QuestionOutcome;
use Database\Factories\QuestionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answered question, for analytics (and usage limits later).
 * Written once, never changed. Deliberately has no user: analytics never show who asked.
 */
class QuestionLog extends Model
{
    /** @use HasFactory<QuestionLogFactory> */
    use HasFactory;

    /** Logs are never updated. */
    public const UPDATED_AT = null;

    /** Everything is set by code with forceCreate(). */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'channel' => QuestionChannel::class,
            'outcome' => QuestionOutcome::class,
            'sources_count' => 'integer',
        ];
    }

    /**
     * Log a question right after its answer was saved (called inside SendMessage's transaction).
     */
    public static function record(Conversation $conversation, string $question, Answer $answer, ConversationMessage $reply): self
    {
        return self::forceCreate([
            ...self::questionAttributes($conversation, $question),
            'conversation_message_id' => $reply->id,
            'outcome' => QuestionOutcome::fromAnswer($answer),
            'sources_count' => count($answer->sources),
            'provider' => $answer->provider,
        ]);
    }

    /**
     * The attributes every log has, whichever way it is created.
     *
     * @return array<string, mixed>
     */
    public static function questionAttributes(Conversation $conversation, string $question): array
    {
        $question = trim($question);

        return [
            'team_id' => $conversation->team_id,
            'channel' => $conversation->isFromWidget() ? QuestionChannel::Widget : QuestionChannel::Chat,
            'question' => $question,
            'normalized_question' => QuestionNormalizer::normalize($question),
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The saved answer (null once its conversation was deleted).
     *
     * @return BelongsTo<ConversationMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
