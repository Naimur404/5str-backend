<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-(anon_id|user_id) rolled-up affinity signals used by the
 * recommendation pipeline.
 *
 * Maintained by `UserSignalsService` on a schedule (hourly), reading
 * from `user_events`. Keeping a derived table — instead of computing
 * the rollup on every recommendation request — turns recommendation
 * generation into an indexed lookup instead of a multi-million-row
 * scan.
 *
 * One row per identity. `signals` is JSON because the shape evolves
 * (today: top categories, top areas, time-of-day, price band; later:
 * brand affinity, business types, attractions vs offerings split,
 * etc.). The recommender just reads the keys it knows about and
 * ignores the rest.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_signals', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Either an anon_id (logged-out tracking) or a user_id
            // (logged-in). One row per identity.
            $table->uuid('anon_id')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Aggregated affinity blob. See UserSignalsService for
            // the canonical shape.
            $table->json('signals');

            // How many events fed this rollup — useful as a coldness
            // gate ("don't personalise users with < 3 signals").
            $table->unsignedInteger('event_count')->default(0);

            // Stamp the aggregator wrote.
            $table->timestamp('rolled_at')->useCurrent();
            $table->timestamps();

            // Either column may be set; both indexed for fast lookup.
            $table->unique('anon_id');
            $table->unique('user_id');
            $table->index('rolled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_signals');
    }
};
