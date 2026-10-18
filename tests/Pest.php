<?php

use App\Ai\Embedder;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A 768-number vector that is zero except for the given positions.
 * Example: searchVector([0 => 1.0]) points along the "first" direction.
 *
 * @param  array<int, float>  $values
 * @return list<float>
 */
function searchVector(array $values): array
{
    $vector = array_fill(0, Embedder::DIMENSIONS, 0.0);

    foreach ($values as $index => $value) {
        $vector[$index] = $value;
    }

    return $vector;
}

/**
 * A ready document in $team with one chunk per [content, embedding] pair.
 * Chunk N gets position N and page N + 1.
 *
 * @param  list<array{0: string, 1: list<float>}>  $chunks
 */
function searchableDocument(Team $team, string $title, array $chunks): Document
{
    $document = Document::factory()->ready()->for($team)->create(['title' => $title]);

    foreach ($chunks as $position => [$content, $embedding]) {
        DocumentChunk::factory()->for($document)->create([
            'position' => $position,
            'page' => $position + 1,
            'content' => $content,
            'embedding' => $embedding,
        ]);
    }

    return $document;
}
