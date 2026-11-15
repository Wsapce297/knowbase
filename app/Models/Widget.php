<?php

namespace App\Models;

use App\Widgets\Origin;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A team's embeddable chat widget.
 *
 * The public key identifies the team in the embed snippet. It is NOT a secret:
 * anyone can read it in a website's source. Protection comes from the allowed
 * origins (browsers refuse to show the widget elsewhere) and rate limiting.
 */
class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;

    /** Makes keys recognisable at a glance (and by secret scanners), like Stripe's "pk_". */
    public const KEY_PREFIX = 'kbw_';

    public const KEY_RANDOM_LENGTH = 32;

    /** What a key looks like, used as a route constraint so malformed keys never reach the database. */
    public const KEY_PATTERN = 'kbw_[A-Za-z0-9]{32}';

    public const DEFAULT_GREETING = "Hi! Ask me a question and I'll answer from our help documents.";

    /**
     * Fields a team can edit in settings. team_id and public_key are set by our own code.
     */
    protected $fillable = [
        'allowed_origins',
        'enabled',
        'greeting',
    ];

    /**
     * Defaults for a brand-new widget (raw database values).
     */
    protected $attributes = [
        'allowed_origins' => '[]',
        'enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Every widget gets a key the moment it is created.
        static::creating(function (Widget $widget): void {
            $widget->public_key ??= self::generateKey();
        });
    }

    /**
     * Str::random() uses PHP's cryptographically secure random_bytes(),
     * so keys can't be predicted. 32 characters make collisions practically impossible.
     */
    public static function generateKey(): string
    {
        return self::KEY_PREFIX.Str::random(self::KEY_RANDOM_LENGTH);
    }

    /**
     * Give the widget a new key. Websites using the old snippet stop working.
     */
    public function regenerateKey(): void
    {
        $this->forceFill(['public_key' => self::generateKey()])->save();
    }

    /**
     * The snippet a team pastes into its website. The loader script (part 5)
     * reads the key from data-key and opens the chat box.
     */
    public function embedCode(): string
    {
        return sprintf(
            '<script src="%s" data-key="%s" async></script>',
            url('widget.js'),
            $this->public_key,
        );
    }

    public function greetingText(): string
    {
        return $this->greeting ?? self::DEFAULT_GREETING;
    }

    /**
     * The Content-Security-Policy value that tells browsers which websites may
     * show the widget page in an iframe. Origins are validated when saved, but are
     * cleaned again here so nothing unexpected can ever reach the header.
     */
    public function frameAncestorsPolicy(): string
    {
        $origins = array_values(array_unique(array_filter(
            array_map(Origin::normalize(...), $this->allowed_origins ?? []),
        )));

        return 'frame-ancestors '.($origins === [] ? "'none'" : implode(' ', $origins));
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
