<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Server-side logging for recommendation impressions and clicks.
 *
 * Why server-side:
 *
 *   - Trust boundary. The mobile client can't be allowed to
 *     fabricate impressions ("we showed you this 1000 times"). The
 *     server is the source of truth for what was actually returned.
 *
 *   - Position bias is the largest confound in offline evaluation.
 *     We need the exact slot order the user saw, not whatever the
 *     client decided to re-render.
 *
 *   - Latency. Sending one network round-trip per impression would
 *     destroy the recommendations endpoint's p95. We bulk-insert
 *     all N impressions in a single statement, after the response
 *     has been queued for write, so the user never waits for the
 *     log.
 *
 * Click-side logging IS still client-driven (we can't observe taps
 * server-side), but we expose a thin endpoint that accepts only the
 * `rec_request_id + business_id` pair and looks up everything else
 * on the server, so a malicious client can't manufacture metadata.
 */
class RecommendationLoggingService
{
    /**
     * Persist one impression row per business returned by the
     * recommender. Caller supplies the same `rec_request_id` the
     * controller put on the response so click logs can join back.
     *
     * @param  Collection<int, \App\Models\Business>|array<int, \App\Models\Business>  $businesses
     */
    public function logImpressions(
        Collection|array $businesses,
        string $recRequestId,
        string $modelVersion,
        ?string $bucket = null,
        ?string $anonId = null,
        ?int $userId = null,
        ?float $latApprox = null,
        ?float $lngApprox = null,
        ?string $userArea = null,
        ?string $platform = null,
        ?string $appVersion = null,
    ): int {
        $rows = [];
        $now = Carbon::now();
        $position = 0;

        foreach ($businesses as $business) {
            $position++;
            $score = $this->extractScore($business);

            // Dedup key includes position so re-rendering the same
            // request_id (extremely rare; should never happen) won't
            // double-count a slot.
            $dedupKey = substr(hash('sha256', sprintf(
                '%s|%d|%d',
                $recRequestId,
                $business->id ?? 0,
                $position,
            )), 0, 64);

            $rows[] = [
                'anon_id' => $anonId ?? Str::uuid()->toString(),
                'user_id' => $userId,
                'event' => 'recommendation_impression',
                'target_type' => 'business',
                'target_id' => $business->id ?? null,
                'category_id' => $business->category_id ?? null,
                'lat_approx' => $latApprox,
                'lng_approx' => $lngApprox,
                'user_area' => $userArea ? mb_substr($userArea, 0, 64) : null,
                'source' => 'recommendations_feed',
                'payload' => null,
                'session_id' => null,
                'app_version' => $appVersion ? mb_substr($appVersion, 0, 16) : null,
                'platform' => $platform ? mb_substr($platform, 0, 16) : null,
                'dedup_key' => $dedupKey,
                'rec_request_id' => $recRequestId,
                'rec_position' => $position,
                'rec_model' => mb_substr($modelVersion, 0, 32),
                'rec_bucket' => $bucket ? mb_substr($bucket, 0, 16) : null,
                'rec_score' => $score,
                'created_at' => $now,
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        try {
            DB::table('user_events')->insertOrIgnore($rows);
        } catch (\Throwable $e) {
            // Logging failures must never bubble up to the user-
            // facing recommendations response. Worst case is a
            // partial gap in the analytics rollup for one request.
            Log::warning('Failed to log recommendation impressions', [
                'rec_request_id' => $recRequestId,
                'count' => count($rows),
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        return count($rows);
    }

    /**
     * Record that an impression was clicked. We look up the matching
     * impression row to get position, model, score — that way the
     * client only needs to send the request id + business id and
     * can't fabricate the rest.
     */
    public function logClick(
        string $recRequestId,
        int $businessId,
        ?string $anonId = null,
        ?int $userId = null,
        ?string $platform = null,
        ?string $appVersion = null,
    ): bool {
        $impression = DB::table('user_events')
            ->where('rec_request_id', $recRequestId)
            ->where('target_id', $businessId)
            ->where('event', 'recommendation_impression')
            ->orderBy('id') // first matching slot wins
            ->first();

        if (! $impression) {
            // No matching impression — refuse to log a phantom click.
            return false;
        }

        $now = Carbon::now();
        $dedupKey = substr(hash('sha256', sprintf(
            'click|%s|%d|%s',
            $recRequestId,
            $businessId,
            $now->format('YmdHis'),
        )), 0, 64);

        try {
            DB::table('user_events')->insertOrIgnore([
                'anon_id' => $anonId ?? $impression->anon_id,
                'user_id' => $userId ?? $impression->user_id,
                'event' => 'recommendation_click',
                'target_type' => 'business',
                'target_id' => $businessId,
                'category_id' => $impression->category_id,
                'lat_approx' => $impression->lat_approx,
                'lng_approx' => $impression->lng_approx,
                'user_area' => $impression->user_area,
                'source' => 'recommendations_feed',
                'payload' => null,
                'session_id' => null,
                'app_version' => $appVersion,
                'platform' => $platform,
                'dedup_key' => $dedupKey,
                'rec_request_id' => $recRequestId,
                'rec_position' => $impression->rec_position,
                'rec_model' => $impression->rec_model,
                'rec_bucket' => $impression->rec_bucket,
                'rec_score' => $impression->rec_score,
                'created_at' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log recommendation click', [
                'rec_request_id' => $recRequestId,
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        return true;
    }

    private function extractScore(mixed $business): ?float
    {
        if (is_object($business) && isset($business->final_score) && is_numeric($business->final_score)) {
            return round((float) $business->final_score, 4);
        }
        return null;
    }
}
