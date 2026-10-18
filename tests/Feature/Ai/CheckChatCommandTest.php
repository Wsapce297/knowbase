<?php

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Prompts\AgentPrompt;

test('the command prints the chat model reply', function () {
    AnonymousAgent::fake(['Knowbase chat is working.']);

    $this->artisan('knowbase:check-chat')
        ->expectsOutputToContain('Knowbase chat is working.')
        ->assertSuccessful();

    AnonymousAgent::assertPrompted(
        fn (AgentPrompt $prompt): bool => $prompt->contains('Knowbase chat connection works'),
    );
});

test('the command can send a custom message', function () {
    AnonymousAgent::fake(['RAG means answering from retrieved documents.']);

    $this->artisan('knowbase:check-chat', ['message' => 'What is RAG?'])
        ->expectsOutputToContain('RAG means answering from retrieved documents.')
        ->assertSuccessful();

    AnonymousAgent::assertPrompted('What is RAG?');
});

test('the command explains what went wrong', function () {
    AnonymousAgent::fake(fn () => throw new RuntimeException('API key not valid.'));

    $this->artisan('knowbase:check-chat')
        ->expectsOutputToContain('API key not valid.')
        ->assertFailed();
});
