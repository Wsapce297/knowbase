<?php

namespace App\Widgets;

/**
 * The secret that lets an anonymous website visitor continue their widget conversation.
 *
 * The iframe keeps the token in localStorage; the database keeps only its sha256 hash,
 * so a leaked database doesn't let anyone read or continue visitors' conversations.
 * A fast hash is fine here (no bcrypt): the token is 48 random hex characters,
 * far too many to guess, unlike a human password. Laravel Sanctum stores API tokens the same way.
 */
final class VisitorToken
{
    public const PREFIX = 'kbv_';

    public const PATTERN = '/^kbv_[a-f0-9]{48}\z/'; // \z, not $: $ would also accept a trailing newline

    public static function generate(): string
    {
        return self::PREFIX.bin2hex(random_bytes(24)); // 24 random bytes = 48 hex characters
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function isWellFormed(string $token): bool
    {
        return preg_match(self::PATTERN, $token) === 1;
    }
}
