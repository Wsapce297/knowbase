<?php

use App\Analytics\QuestionNormalizer;

test('questions are normalized for grouping', function (string $question, string $expected) {
    expect(QuestionNormalizer::normalize($question))->toBe($expected);
})->with([
    'punctuation' => ['How do refunds work?', 'how do refunds work'],
    'case and spaces' => ['  HOW   do refunds   work ??? ', 'how do refunds work'],
    'apostrophes join words' => ["What's the price?", 'whats the price'],
    'curly apostrophe' => ['What’s the price?', 'whats the price'],
    'numbers stay' => ['Refund policy (2026)', 'refund policy 2026'],
    'other languages' => ['¿Cómo funciona el REEMBOLSO?', 'cómo funciona el reembolso'],
    'only punctuation' => ['???', ''],
]);
