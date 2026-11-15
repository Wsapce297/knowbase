<?php

namespace Database\Factories;

use App\Analytics\QuestionNormalizer;
use App\Enums\QuestionChannel;
use App\Enums\QuestionOutcome;
use App\Models\QuestionLog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Use ->for($team) to choose the team, ->asking('...') for the text.
 *
 * @extends Factory<QuestionLog>
 */
class QuestionLogFactory extends Factory
{
    public function definition(): array
    {
        $question = fake()->sentence().'?';

        return [
            'team_id' => Team::factory(),
            'conversation_message_id' => null,
            'channel' => QuestionChannel::Chat,
            'question' => $question,
            'normalized_question' => QuestionNormalizer::normalize($question),
            'outcome' => QuestionOutcome::Answered,
            'sources_count' => 3,
            'provider' => 'gemini',
        ];
    }

    public function asking(string $question): static
    {
        return $this->state(fn () => [
            'question' => $question,
            'normalized_question' => QuestionNormalizer::normalize($question),
        ]);
    }

    public function notFound(): static
    {
        return $this->state(fn () => [
            'outcome' => QuestionOutcome::NotFound,
            'sources_count' => 0,
            'provider' => null,
        ]);
    }

    public function uncited(): static
    {
        return $this->state(fn () => ['outcome' => QuestionOutcome::Uncited]);
    }

    public function fromWidget(): static
    {
        return $this->state(fn () => ['channel' => QuestionChannel::Widget]);
    }
}
