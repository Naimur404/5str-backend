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
        Schema::create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            
            // Interaction types
             $table->enum('interaction_type', [
                'view', 'click', 'save', 'share', 'visit', 'call', 'direction',
                'search_click', 'phone_call', 'favorite', 'unfavorite', 'review',
                'collection_add', 'collection_remove', 'offer_view', 'offer_use',
                'direction_request', 'website_click'
            ]);
            $table->decimal('interaction_value', 3, 2)->default(0.1);
            
            // Interaction context
            $table->string('source')->nullable(); // search, collection, home, etc.
            $table->json('context_data')->nullable(); // Additional context
            
            // Interaction strength/weight
            $table->decimal('weight', 5, 2)->default(1.0); // Higher = stronger signal
            
            // Session and location data
            $table->string('session_id')->nullable();
            $table->decimal('user_latitude', 10, 8)->nullable();
            $table->decimal('user_longitude', 11, 8)->nullable();
            
            // Time data
            $table->timestamp('interaction_time')->useCurrent();
            $table->timestamps();
            
            // Indexes for ML queries
            $table->index(['user_id', 'interaction_type']);
            $table->index(['business_id', 'interaction_type']);
            $table->index(['user_id', 'business_id']);
            $table->index('interaction_time');
            $table->index(['user_id', 'interaction_time']);
            $table->index('weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_interactions');
    }
};
