<?php

use App\Documents\Chunking\Chunk;
use App\Documents\Chunking\TextChunker;
use App\Documents\Extraction\ExtractedPage;

/**
 * Builds "Sentence 1 about refunds. Sentence 2 about refunds. ..."
 */
function chunkerSentences(int $count, string $topic = 'refunds'): string
{
    return implode(' ', array_map(
        fn (int $i): string => "Sentence {$i} about {$topic}.",
        range(1, $count),
    ));
}

it('keeps short text in a single chunk', function () {
    $chunks = (new TextChunker)->chunk([new ExtractedPage(null, 'A short note.')]);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0])->toBeInstanceOf(Chunk::class)
        ->and($chunks[0]->position)->toBe(0)
        ->and($chunks[0]->page)->toBeNull()
        ->and($chunks[0]->content)->toBe('A short note.');
});

it('never makes a chunk longer than the chunk size', function () {
    $chunks = (new TextChunker(size: 200, overlap: 40))->split(chunkerSentences(100));

    expect(count($chunks))->toBeGreaterThan(5);

    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(200);
    }
});

it('repeats the end of each chunk at the start of the next one', function () {
    $chunks = (new TextChunker(size: 200, overlap: 60))->split(chunkerSentences(40));

    foreach (array_slice($chunks, 1) as $index => $chunk) {
        $firstSentence = strtok($chunk, '.').'.';

        expect($chunks[$index])->toContain($firstSentence);
    }
});

it('loses no text', function () {
    $text = chunkerSentences(60);

    $chunks = (new TextChunker(size: 150, overlap: 30))->split($text);

    foreach (range(1, 60) as $i) {
        expect(implode(' ', $chunks))->toContain("Sentence {$i} about refunds.");
    }
});

it('keeps paragraphs together when they fit', function () {
    $text = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";

    expect((new TextChunker(size: 100, overlap: 10))->split($text))
        ->toBe(["First paragraph.\n\nSecond paragraph.\n\nThird paragraph."]);
});

it('cuts a very long word into pieces', function () {
    $chunks = (new TextChunker(size: 50, overlap: 10))->split(str_repeat('a', 120));

    expect($chunks)->toBe([str_repeat('a', 50), str_repeat('a', 50), str_repeat('a', 20)]);
});

it('numbers chunks across pages and keeps each page number', function () {
    $chunks = (new TextChunker(size: 200, overlap: 40))->chunk([
        new ExtractedPage(1, chunkerSentences(20, 'refunds')),
        new ExtractedPage(3, chunkerSentences(20, 'shipping')),
    ]);

    expect(array_map(fn (Chunk $c) => $c->position, $chunks))->toBe(range(0, count($chunks) - 1));

    foreach ($chunks as $chunk) {
        $topic = $chunk->page === 1 ? 'refunds' : 'shipping';

        expect($chunk->page)->toBeIn([1, 3])
            ->and($chunk->content)->toContain($topic)
            ->and($chunk->content)->not->toContain($topic === 'refunds' ? 'shipping' : 'refunds');
    }
});

it('drops chunks without letters or digits', function () {
    $chunks = (new TextChunker)->chunk([
        new ExtractedPage(1, '   '),
        new ExtractedPage(2, '* * * --- ...'),
        new ExtractedPage(3, 'Real content.'),
    ]);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->position)->toBe(0)
        ->and($chunks[0]->page)->toBe(3);
});

it('rejects an invalid size or overlap', function (int $size, int $overlap) {
    expect(fn () => new TextChunker($size, $overlap))->toThrow(InvalidArgumentException::class);
})->with([
    'size zero' => [0, 0],
    'negative overlap' => [100, -1],
    'overlap as big as size' => [100, 100],
]);
