<?php

namespace App\Ai;

use Laravel\Ai\Embeddings;
use RuntimeException;

/**
 * The one place in Knowbase that turns text into embeddings.
 *
 * Everything else (the document job, the chat search) calls this class,
 * so the provider, prefixes, batching and size check live in one spot.
 */
class Embedder
{
    /**
     * Size of every embedding. Must match the vector(768) database column.
     */
    public const DIMENSIONS = 768;

    /**
     * How many chunks are sent to the provider in one request.
     */
    public const BATCH_SIZE = 32;

    /**
     * Embed document chunks (used when a document is uploaded).
     *
     * @param  list<string>  $texts
     * @return list<list<float>> One embedding per text, in the same order.
     */
    public function documents(array $texts): array
    {
        $vectors = [];

        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            $inputs = array_map(fn (string $text): string => 'search_document: '.$text, $batch);

            array_push($vectors, ...$this->embed($inputs));
        }

        return $vectors;
    }

    /**
     * Embed a user's question (used when searching).
     *
     * @return list<float>
     */
    public function query(string $text): array
    {
        return $this->embed(['search_query: '.$text])[0];
    }

    /**
     * @param  list<string>  $inputs
     * @return list<list<float>>
     */
    private function embed(array $inputs): array
    {
        $embeddings = Embeddings::for($inputs)
            ->timeout(120)
            ->generate()
            ->embeddings;

        foreach ($embeddings as $embedding) {
            if (count($embedding) !== self::DIMENSIONS) {
                throw new RuntimeException(sprintf(
                    'Expected %d-dimension embeddings but received %d. Check the embeddings settings in config/ai.php.',
                    self::DIMENSIONS,
                    count($embedding),
                ));
            }
        }

        return array_values($embeddings);
    }
}
