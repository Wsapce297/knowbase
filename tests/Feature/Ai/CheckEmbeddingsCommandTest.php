<?php

use Laravel\Ai\Embeddings;

it('reports the size of a test embedding', function () {
    Embeddings::fake();

    $this->artisan('knowbase:check-embeddings', ['text' => 'Hello'])
        ->expectsOutputToContain('Success: received 768 numbers.')
        ->assertSuccessful();
});

it('shows a friendly error when embedding fails', function () {
    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    $this->artisan('knowbase:check-embeddings')
        ->expectsOutputToContain('Failed: Expected 768-dimension embeddings')
        ->assertFailed();
});
