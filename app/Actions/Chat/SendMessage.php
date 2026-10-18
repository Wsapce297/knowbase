<?php

namespace App\Actions\Chat;

use App\Ai\Answer;
use App\Ai\AskKnowbase;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\DB;

/**
 * Ask a question inside a conversation and save both the question and the answer.
 */
class SendMessage
{
    /** How many earlier messages the model sees (3 questions + 3 answers). */
    public const HISTORY_MESSAGES = 6;

    public function __construct(private AskKnowbase $knowbase) {}

    /**
     * @return ConversationMessage The assistant's reply.
     */
    public function handle(Conversation $conversation, string $question): ConversationMessage
    {
        $question = trim($question);

        // Ask first. If the AI fails, nothing is saved and the user can simply try again.
        $answer = $this->knowbase->ask($conversation->team, $question, $this->history($conversation));

        return DB::transaction(function () use ($conversation, $question, $answer): ConversationMessage {
            $conversation->messages()->create([
                'role' => MessageRole::User,
                'content' => $question,
            ]);

            $reply = $conversation->messages()->create([
                'role' => MessageRole::Assistant,
                'content' => $answer->text,
                'sources' => $this->sourcesFor($answer),
                'provider' => $answer->provider,
            ]);

            $conversation->touch(); // moves it to the top of the conversation list

            return $reply;
        });
    }

    /**
     * The last few messages, oldest first, in the shape laravel/ai expects.
     *
     * Old answers lose their [1] citation markers: those numbers pointed to
     * old sources and would confuse the model when new sources get numbered.
     *
     * @return list<array{role: string, content: string}>
     */
    private function history(Conversation $conversation): array
    {
        return $conversation->messages()
            ->reorder('id', 'desc')
            ->limit(self::HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->map(fn (ConversationMessage $message): array => [
                'role' => $message->role->value,
                'content' => $message->role === MessageRole::Assistant
                    ? trim(preg_replace('/\s*\[\d+(?:\s*,\s*\d+)*\]/', '', $message->content))
                    : $message->content,
            ])
            ->values()
            ->all();
    }

    /**
     * Sources saved with the answer, ready for the chat page:
     * a copy of each passage, so citations still work if a document is deleted later.
     *
     * @return list<array<string, mixed>>
     */
    private function sourcesFor(Answer $answer): array
    {
        $cited = $answer->citedNumbers();
        $sources = [];

        foreach ($answer->sources as $index => $source) {
            $number = $index + 1;

            $sources[] = [
                'number' => $number,
                'documentId' => $source->documentId,
                'documentTitle' => $source->documentTitle,
                'page' => $source->page,
                'content' => $source->content,
                'similarity' => $source->similarity,
                'cited' => in_array($number, $cited, true),
            ];
        }

        return $sources;
    }
}
