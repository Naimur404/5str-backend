<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only behaviour event store powering recommendations,
 * personalised home, and analytics dashboards.
 *
 * Design choices:
 *
 *   - `anon_id` is the primary identity. The mobile client generates
 *     one v4 UUID per install and persists it; that's what is sent
 *     for every event. `user_id` is filled in only when a logged-in
 *     user is making the call. This keeps tracking working while the
 *     user is anonymous (Play-policy safe) and avoids storing any
 *     hardware/device identifiers we don't need.
 *
 *   - `event` is a free-form short string (`business_view`,
 *     `search`, `favorite_added`, `offer_used`, `home_open`, …). We
 *     intentionally don't model an enum so new event types don't need
 *     a migration; the recommender only cares about a known subset.
 *
 *   - `target_type` / `target_id` form a polymorphic pointer to the
 *     thing the user interacted with (Business, BusinessOffering,
 *     Attraction, Offer, Category, …).
 *
 *   - `lat_approx` / `lng_approx` are stored at 2-decimal precision
 *     (~1km) so we never accumulate exact-GPS history about anyone.
 *     The mobile client is expected to round before sending.
 *
 *   - `payload` JSON is for event-specific extras (search query,
 *     results count, offer code, source screen, …). Indexed events
 *     are the structured columns; everything else is opaque.
 *
 *   - `dedup_key` is set by the client (or derived) so a network
 *     retry doesn't double-record the same event. Unique index makes
 *     replay safe.
 *
 *   - All time-range queries use (`anon_id`, `created_at`) or
 *     (`user_id`, `created_at`) so the per-user signal aggregator is
 *     index-only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Primary identity. Always present.
            $table->uuid('anon_id')->index();

            // Filled when a logged-in user emitted the event.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('event', 64);

            // Polymorphic target — what they interacted with.
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            // Optional category context (set by the client when a
            // search or browse happens within a category — saves a
            // join during signal aggregation).
            $table->unsignedBigInteger('category_id')->nullable();

            // Approximate location (~1km), JSON-flat for cheap reads.
            $table->decimal('lat_approx', 5, 2)->nullable();
            $table->decimal('lng_approx', 6, 2)->nullable();
            $table->string('user_area', 64)->nullable();

            // Source screen (search_results, home_featured, brand_page, …).
            $table->string('source', 32)->nullable();

            // Free-form extras.
            $table->json('payload')->nullable();

            // Client batch metadata.
            $table->string('session_id', 64)->nullable();
            $table->string('app_version', 16)->nullable();
            $table->string('platform', 16)->nullable(); // android | ios | web

            // Idempotency / retry safety. NULL = ungated event.
            $table->string('dedup_key', 96)->nullable()->unique();

            // Server-stamped at insert.
            $table->timestamp('created_at')->useCurrent();

            // Hot-path indexes.
            $table->index(['anon_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index('user_area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_events');
    }
};
