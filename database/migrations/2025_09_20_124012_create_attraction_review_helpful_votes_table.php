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
        Schema::create('attraction_review_helpful_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('attraction_reviews')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_helpful')->default(true); // true = helpful, false = not helpful
            $table->timestamps();

            // Indexes
            $table->index(['review_id', 'user_id']);
            $table->index(['user_id', 'review_id']);
            
            // Unique constraint - one vote per user per review
            $table->unique(['review_id', 'user_id'], 'unique_review_user_vote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attraction_review_helpful_votes');
    }
};
