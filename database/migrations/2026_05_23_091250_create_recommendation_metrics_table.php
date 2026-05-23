<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily aggregate of impression + click counts per
 * (model, bucket, slot_position) so the admin dashboard does not
 * have to re-scan `user_events` (millions of rows) every time
 * someone opens the page.
 *
 * Refilled by `recommendations:rollup-metrics` overnight; we keep
 * 90 days of rollups in line with the rest of analytics retention.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('recommendation_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('day');
            $table->string('model', 32);
            $table->string('bucket', 16)->nullable();
            $table->unsignedSmallInteger('position');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('ctr', 8, 6)->default(0);
            $table->decimal('avg_score', 6, 4)->nullable();
            $table->timestamps();

            $table->unique(['day', 'model', 'bucket', 'position'], 'rec_metrics_unique');
            $table->index(['model', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_metrics');
    }
};
