<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates and persists semantic embeddings for businesses.
 *
 * What we embed:
 *   business_name + description (truncated)
 *   + category name
 *   + product/business tags
 *   + first N offering names (so a Pizzeria's menu actually
 *     informs its "what is this place about" vector)
 *
 * Why this matters for recommendations:
 *   - Keyword matching can't tell a user "good for date night"
 *     where the business listing literally says
 *     "romantic candlelit Italian dinner". Vectors can.
 *   - Once embeddings exist, hybrid search (BM25 + vector) finds
 *     intent matches that pure category filters miss.
 *
 * Cost control:
 *   - SHA-256 hash of the source text is stored alongside the
 *     vector. If the hash hasn't changed since the last embed,
 *     the OpenAI call is skipped entirely.
 *   - text-embedding-3-small is the cheapest model that handles
 *     mixed-language text well; runs $0.02/1M tokens. Embedding
 *     a full 1k-business catalog costs ~$0.10.
 */
class BusinessEmbeddingService
{
    private const MAX_DESCRIPTION_CHARS = 1500;
    private const MAX_OFFERINGS_INCLUDED = 8;

    public function embed(Business $business, bool $force = false): bool
    {
        $sourceText = $this->buildSourceText($business);
        $sourceHash = hash('sha256', $sourceText);

        // Skip when nothing material changed.
        if (! $force && $business->embedding_hash === $sourceHash && $business->embedding) {
            return false;
        }

        $vector = $this->callEmbeddingApi($sourceText);
        if ($vector === null) {
            return false;
        }

        $business->forceFill([
            'embedding' => $vector,
            'embedding_hash' => $sourceHash,
            'embedding_updated_at' => now(),
        ])->saveQuietly();

        return true;
    }

    /**
     * Embed a query string at request time. We hand it to
     * OpenAI directly (no caching) because each query is unique.
     *
     * @return list<float>|null
     */
    public function embedQuery(string $query): ?array
    {
        $query = trim(mb_substr($query, 0, 500));
        if ($query === '') return null;
        return $this->callEmbeddingApi($query);
    }

    /**
     * Cosine similarity between two normalised vectors. Both must
     * be the same length; returns null when shapes mismatch so the
     * caller can fall back to keyword.
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public function cosine(array $a, array $b): ?float
    {
        $n = count($a);
        if ($n === 0 || $n !== count($b)) return null;

        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }
        if ($magA <= 0 || $magB <= 0) return 0.0;
        return $dot / (sqrt($magA) * sqrt($magB));
    }

    private function buildSourceText(Business $business): string
    {
        $business->loadMissing(['category:id,name', 'offerings:id,business_id,name']);

        $parts = [];
        $parts[] = (string) $business->business_name;

        $description = mb_substr((string) ($business->description ?? ''), 0, self::MAX_DESCRIPTION_CHARS);
        if ($description !== '') {
            $parts[] = $description;
        }

        if ($business->category) {
            $parts[] = 'Category: ' . $business->category->name;
        }

        if (is_array($business->business_tags) && ! empty($business->business_tags)) {
            $parts[] = 'Tags: ' . implode(', ', array_slice($business->business_tags, 0, 20));
        }
        if (is_array($business->product_tags) && ! empty($business->product_tags)) {
            $parts[] = 'Products: ' . implode(', ', array_slice($business->product_tags, 0, 20));
        }

        $offeringNames = $business->offerings->take(self::MAX_OFFERINGS_INCLUDED)
            ->pluck('name')
            ->filter()
            ->all();
        if (! empty($offeringNames)) {
            $parts[] = 'Menu / services: ' . implode(', ', $offeringNames);
        }

        return implode("\n", $parts);
    }

    /**
     * @return list<float>|null
     */
    private function callEmbeddingApi(string $text): ?array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.embedding_model', 'text-embedding-3-small');

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('services.openai.request_timeout', 30))
                ->retry(2, 250)
                ->post("$baseUrl/embeddings", [
                    'model' => $model,
                    'input' => $text,
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI embedding API non-200', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $vector = $response->json('data.0.embedding');
            if (! is_array($vector) || empty($vector)) {
                return null;
            }

            return array_map('floatval', $vector);
        } catch (\Throwable $e) {
            Log::warning('OpenAI embedding API exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
