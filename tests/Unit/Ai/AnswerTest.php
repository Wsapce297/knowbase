<?php

use App\Ai\Answer;
use App\Ai\SearchResult;

function answerSources(int $count): array
{
    return array_map(
        fn (int $i): SearchResult => new SearchResult($i, 1, 'Doc', null, "Passage {$i}", 0.9),
        range(1, $count),
    );
}

it('finds every citation style the model may use', function () {
    $answer = new Answer('First [2]. Second [1][3]. Third [1, 3].', answerSources(3));

    expect($answer->citedNumbers())->toBe([1, 2, 3]);
});

it('ignores numbers that are not sources', function () {
    $answer = new Answer('Real [1]. Made up [7]. Zero [0].', answerSources(2));

    expect($answer->citedNumbers())->toBe([1])
        ->and((new Answer('No sources here [1].', []))->citedNumbers())->toBe([]);
});
