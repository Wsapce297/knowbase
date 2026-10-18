<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Widget>
 */
class WidgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'public_key' => Widget::generateKey(),
            'allowed_origins' => ['https://example.com'],
            'enabled' => true,
            'greeting' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }

    /**
     * @param  list<string>  $origins
     */
    public function allowingOrigins(array $origins): static
    {
        return $this->state(['allowed_origins' => $origins]);
    }
}
