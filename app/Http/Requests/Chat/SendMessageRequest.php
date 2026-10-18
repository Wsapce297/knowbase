<?php

namespace App\Http\Requests\Chat;

use App\Models\Conversation;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A chat question: either the first one (starts a conversation in the
 * current team) or a follow-up in an existing conversation.
 */
class SendMessageRequest extends FormRequest
{
    public const MAX_LENGTH = 2000;

    private ?Conversation $conversation = null;

    public function authorize(): bool
    {
        $conversation = $this->conversation();

        return $conversation
            ? $this->user()->can('update', $conversation)
            : $this->user()->can('create', [Conversation::class, $this->team()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'conversation_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Type a question first.',
            'question.max' => 'Questions can be at most '.self::MAX_LENGTH.' characters.',
        ];
    }

    /**
     * The conversation being continued, or null for a new one.
     * An id that doesn't exist gives a 404.
     */
    public function conversation(): ?Conversation
    {
        $id = $this->input('conversation_id');

        if (! is_numeric($id)) {
            return null; // empty, or not a number (the "integer" rule reports that)
        }

        return $this->conversation ??= Conversation::findOrFail((int) $id);
    }

    public function team(): Team
    {
        return Team::findOrFail($this->user()->current_team_id);
    }
}
