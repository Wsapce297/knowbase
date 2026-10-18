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
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
