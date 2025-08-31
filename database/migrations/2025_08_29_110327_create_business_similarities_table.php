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
        Schema::create('business_similarities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_a_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('business_b_id')->constrained('businesses')->onDelete('cascade');
            $table->string('similarity_type', 50); // 'category_similar', 'location_similar', etc.
            $table->decimal('similarity_score', 5, 4); // 0-1 scale
            $table->json('contributing_factors')->nullable(); // Store detailed factor breakdown
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();
            
            // Ensure no duplicate pairs for same type and optimize lookups
            $table->unique(['business_a_id', 'business_b_id', 'similarity_type'], 'business_sim_unique');
            $table->index(['business_a_id', 'similarity_score'], 'business_sim_a_score');
            $table->index(['business_b_id', 'similarity_score'], 'business_sim_b_score');
            $table->index(['similarity_type', 'similarity_score'], 'business_sim_type_score');
            $table->index('calculated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_similarities');
    }
};
