<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('rating_criteria')->nullable()->after('description');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->json('ratings')->nullable()->after('overall_rating');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->json('rating_averages')->nullable()->after('overall_rating');
            $table->json('rating_distribution')->nullable()->after('total_reviews');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('rating_criteria');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('ratings');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['rating_averages', 'rating_distribution']);
        });
    }
};
