<?php

namespace App\Models;

use App\Enums\MessageRole;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    protected $fillable = ['role', 'content', 'sources', 'provider'];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'sources' => 'array', // JSON in the database, a PHP array in code
        ];
    }

    /**
     * The shape the chat page works with (camelCase keys, like other pages).
     *
     * @return array{id: int, role: string, content: string, sources: list<array<string, mixed>>, createdAt: string}
     */
    public function toChatArray(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content,
            'sources' => $this->sources ?? [],
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
