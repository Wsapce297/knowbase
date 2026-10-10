<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'personal_team' => false,
        ];
    }

    public function personal(): static
    {
        return $this->state(['personal_team' => true]);
    }

    public function ownedBy(User $user): static
    {
        return $this->afterCreating(function (Team $team) use ($user): void {
            $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        });
    }
}
