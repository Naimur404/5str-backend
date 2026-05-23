<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the columns we need to evaluate recommendation quality.
 *
 *   - rec_request_id  : groups every event from a single
 *                       /api/v1/recommendations call so we can
 *                       compute CTR per request, slot, and model
 *                       version. Indexed because every join into
 *                       this table for analytics will be on it.
 *   - rec_position    : 1..N slot the item was shown in. Position
 *                       bias is the single biggest confound in
 *                       offline evaluation, so we record it.
 *   - rec_model       : the recommender configuration the row came
 *                       from ("light_v1", "full_v2_freshness", …).
 *                       Required for A/B reads later.
 *   - rec_bucket      : optional explicit experiment bucket
 *                       ("control", "challenger_a") so we can carve
 *                       traffic without changing the model name.
 *   - rec_score       : the relevance score the model assigned at
 *                       impression time. Lets us correlate
 *                       confidence with click-through.
 *
 * Schema is additive and nullable — every existing event keeps
 * working unchanged. Legacy `business_view`/`search` rows simply
 * leave these columns NULL.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->uuid('rec_request_id')->nullable()->after('source');
            $table->unsignedSmallInteger('rec_position')->nullable()->after('rec_request_id');
            $table->string('rec_model', 32)->nullable()->after('rec_position');
            $table->string('rec_bucket', 16)->nullable()->after('rec_model');
            $table->decimal('rec_score', 6, 4)->nullable()->after('rec_bucket');

            // Hot-path analytics index: rolling up CTR per
            // (request, position) requires a covering scan; this
            // index makes that one logical seek.
            $table->index(['rec_request_id', 'rec_position'], 'user_events_rec_request_pos_idx');

            // Daily rollup queries hit (event, model, created_at).
            $table->index(['rec_model', 'event', 'created_at'], 'user_events_rec_model_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->dropIndex('user_events_rec_model_event_idx');
            $table->dropIndex('user_events_rec_request_pos_idx');
            $table->dropColumn([
                'rec_request_id',
                'rec_position',
                'rec_model',
                'rec_bucket',
                'rec_score',
            ]);
        });
    }
};
