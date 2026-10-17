<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'uploaded_by',
        'title',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'status',
        'error',
        'processed_at',
    ];

    /**
     * Default values for new models (matches the database default).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => DocumentStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'size' => 'integer',
            'processed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<DocumentChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
