<?php

use App\Ai\Embedder;
use Illuminate\Http\Client\StrayRequestException;

test('tests cannot reach a real AI provider', function () {
    // No Embeddings::fake() here, so this would call Ollama for real.
    expect(fn () => app(Embedder::class)->query('Hello'))
        ->toThrow(StrayRequestException::class);
});
