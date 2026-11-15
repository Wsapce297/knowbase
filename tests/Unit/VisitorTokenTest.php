<?php

use App\Widgets\VisitorToken;

test('generated tokens are well formed and different every time', function () {
    $first = VisitorToken::generate();
    $second = VisitorToken::generate();

    expect(VisitorToken::isWellFormed($first))->toBeTrue()
        ->and(strlen($first))->toBe(52)
        ->and($first)->not->toBe($second);
});

test('the hash is a stable sha256 that does not contain the token', function () {
    $token = VisitorToken::generate();

    expect(VisitorToken::hash($token))
        ->toBe(VisitorToken::hash($token))
        ->toMatch('/^[a-f0-9]{64}$/')
        ->not->toContain(substr($token, 4));
});

test('badly formed tokens are recognised', function (string $token) {
    expect(VisitorToken::isWellFormed($token))->toBeFalse();
})->with([
    'empty' => [''],
    'no prefix' => [str_repeat('a', 48)],
    'too short' => ['kbv_'.str_repeat('a', 47)],
    'upper case' => ['kbv_'.str_repeat('A', 48)],
    'trailing newline' => ['kbv_'.str_repeat('a', 48)."\n"],
]);
