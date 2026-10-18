<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => MessageRole::User,
            'content' => fake()->sentence().'?',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    public function assistant(array $sources = []): static
    {
        return $this->state(fn () => [
            'role' => MessageRole::Assistant,
            'content' => fake()->sentence(),
            'sources' => $sources,
            'provider' => 'gemini',
        ]);
    }
}
