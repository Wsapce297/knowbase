<?php

use App\Ai\Answer;
use App\Ai\SearchResult;
use App\Enums\QuestionOutcome;

function outcomeSource(): SearchResult
{
    return new SearchResult(
        chunkId: 1,
        documentId: 1,
        documentTitle: 'Refund Policy',
        page: 1,
        content: 'Refunds within 30 days.',
        similarity: 0.8,
    );
}

test('an answer citing a source is answered', function () {
    expect(QuestionOutcome::fromAnswer(new Answer('Within 30 days [1].', [outcomeSource()])))
        ->toBe(QuestionOutcome::Answered);
});

test('an answer without sources is not found', function () {
    expect(QuestionOutcome::fromAnswer(new Answer('I could not find anything.', [])))
        ->toBe(QuestionOutcome::NotFound);
});

test('an answer with sources but no citation is uncited', function () {
    expect(QuestionOutcome::fromAnswer(new Answer('The documents do not say.', [outcomeSource()])))
        ->toBe(QuestionOutcome::Uncited);
});

test('the outcome can be worked out from saved sources', function (array $sources, QuestionOutcome $expected) {
    expect(QuestionOutcome::fromStoredSources($sources))->toBe($expected);
})->with([
    'no sources' => [[], QuestionOutcome::NotFound],
    'one cited' => [[['cited' => false], ['cited' => true]], QuestionOutcome::Answered],
    'none cited' => [[['cited' => false]], QuestionOutcome::Uncited],
    'missing flag' => [[['number' => 1]], QuestionOutcome::Uncited],
]);
