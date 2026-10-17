<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true));

        return [
            'team_id' => Team::factory(),
            'uploaded_by' => null,
            'title' => $title,
            'original_name' => Str::slug($title).'.md',
            'disk' => 'local',
            'path' => 'documents/'.Str::random(40).'.md',
            'mime_type' => 'text/markdown',
            'size' => fake()->numberBetween(1_000, 500_000),
            'status' => DocumentStatus::Pending,
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => DocumentStatus::Processing]);
    }

    public function ready(): static
    {
        return $this->state([
            'status' => DocumentStatus::Ready,
            'processed_at' => now(),
        ]);
    }

    public function failed(string $error = 'The file could not be read.'): static
    {
        return $this->state([
            'status' => DocumentStatus::Failed,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }
}
