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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Category preferences
            $table->json('preferred_categories')->nullable(); // Array of category IDs
            $table->json('category_weights')->nullable(); // Weighted preferences
            
            // Price range preferences
            $table->integer('min_price_range')->default(1);
            $table->integer('max_price_range')->default(5);
            
            // Location preferences
            $table->decimal('preferred_radius', 8, 2)->default(10.0); // km
            $table->json('preferred_areas')->nullable(); // Array of area names
            
            // Feature preferences
            $table->boolean('prefers_delivery')->default(false);
            $table->boolean('prefers_pickup')->default(false);
            $table->boolean('prefers_parking')->default(false);
            $table->boolean('prefers_verified')->default(true);
            
            // Rating preferences
            $table->decimal('min_rating', 3, 2)->default(0.0);
            
            // Time-based preferences
            $table->json('preferred_hours')->nullable(); // When user typically searches
            
            // Behavioral data
            $table->integer('search_frequency')->default(0);
            $table->integer('review_frequency')->default(0);
            $table->integer('collection_frequency')->default(0);
            
            // Learning weights (for ML algorithms)
            $table->json('ml_weights')->nullable(); // Algorithm-specific weights
            
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index(['min_price_range', 'max_price_range']);
            $table->index('last_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
