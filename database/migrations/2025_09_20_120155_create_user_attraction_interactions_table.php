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
        Schema::create('user_attraction_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('attraction_id')->constrained('attractions')->onDelete('cascade');
            $table->string('interaction_type'); // like, dislike, share, bookmark, visit, check_in
            $table->json('interaction_data')->nullable(); // additional data like share platform, check-in location, etc.
            $table->text('notes')->nullable(); // user's personal notes about the attraction
            $table->json('visit_info')->nullable(); // visit date, companions, photos, etc.
            $table->boolean('is_public')->default(true); // whether interaction is public or private
            $table->decimal('user_rating', 2, 1)->nullable(); // personal rating separate from review
            $table->boolean('is_active')->default(true);
            $table->timestamp('interaction_date')->nullable();
            $table->timestamps();

            // Indexes with custom shorter names
            $table->index(['user_id', 'attraction_id'], 'uai_user_attraction_idx');
            $table->index(['attraction_id', 'interaction_type'], 'uai_attraction_type_idx');
            $table->index(['user_id', 'interaction_type'], 'uai_user_type_idx');
            $table->index('interaction_date', 'uai_date_idx');
            
            // Unique constraint for specific interaction types
            $table->unique(['user_id', 'attraction_id', 'interaction_type'], 'uai_unique_interaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_attraction_interactions');
    }
};
