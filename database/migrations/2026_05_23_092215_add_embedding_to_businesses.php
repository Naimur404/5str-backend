<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business semantic embedding for fuzzy / intent search.
 *
 * Storage choice — JSON column, not BLOB:
 *   - text-embedding-3-small returns 1536 floats (~12KB packed).
 *     JSON keeps the data inspectable and lets MariaDB skip parsing
 *     in the ingest path.
 *   - We don't run vector search inside MariaDB. The embedding is
 *     pushed to Meilisearch (which has native vector support since
 *     1.6) so retrieval happens there in O(log n).
 *
 * `embedding_hash` is a SHA-256 of (name + description + tags +
 * category_id + offering_names) so the embedding job can skip
 * regeneration when nothing material changed. Saves ~99% of the
 * OpenAI bill on incremental re-embeds.
 *
 * `embedding_updated_at` tells the recommender how stale the
 * vector is — the freshness pass uses it as a tertiary tiebreaker
 * during the migration window when not every business has been
 * embedded yet.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->json('embedding')->nullable()->after('discovery_score');
            $table->string('embedding_hash', 64)->nullable()->after('embedding');
            $table->timestamp('embedding_updated_at')->nullable()->after('embedding_hash');
            $table->index('embedding_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['embedding_updated_at']);
            $table->dropColumn(['embedding', 'embedding_hash', 'embedding_updated_at']);
        });
    }
};
