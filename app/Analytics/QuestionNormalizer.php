<?php

namespace App\Analytics;

/**
 * Turns a question into a simple form for grouping identical questions:
 *
 *   "  How do REFUNDS work??? "  →  "how do refunds work"
 *   "What's the price?"          →  "whats the price"
 *
 * Lowercase, punctuation removed, spaces squeezed. Works with any language
 * (\p{L} = any letter, \p{N} = any digit). Pure PHP, no Laravel needed.
 *
 * It only merges questions written the same way; "refund policy?" and
 * "how do I get a refund" stay separate. Grouping by meaning (with embeddings)
 * would be a possible later improvement.
 */
final class QuestionNormalizer
{
    public static function normalize(string $question): string
    {
        $text = mb_strtolower($question, 'UTF-8');

        // Apostrophes join words ("what's" → "whats"); other punctuation separates them.
        $text = preg_replace("/['’`]/u", '', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
