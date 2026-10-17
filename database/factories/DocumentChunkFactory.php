<?php

namespace Database\Factories;

use App\Ai\Embedder;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Ai\Embeddings;

/**
 * @extends Factory<DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            // Always copy the team from the chunk's document.
            'team_id' => fn (array $attributes) => Document::query()->findOrFail($attributes['document_id'])->team_id,
            'position' => fake()->unique()->numberBetween(0, 999_999),
            'page' => null,
            'content' => fake()->paragraph(),
            'embedding' => Embeddings::fakeEmbedding(Embedder::DIMENSIONS),
        ];
    }
}
