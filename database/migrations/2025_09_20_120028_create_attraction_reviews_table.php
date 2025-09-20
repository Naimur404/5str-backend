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
        Schema::create('attraction_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attraction_id')->constrained('attractions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('rating', 2, 1); // 0.0 to 5.0
            $table->string('title')->nullable();
            $table->text('comment');
            $table->json('visit_info')->nullable(); // visit date, duration, companions, etc.
            $table->json('experience_tags')->nullable(); // fun, peaceful, crowded, etc.
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->integer('helpful_votes')->default(0);
            $table->integer('total_votes')->default(0);
            $table->string('status')->default('active'); // active, hidden, flagged, deleted
            $table->text('admin_notes')->nullable();
            $table->timestamp('visit_date')->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['attraction_id', 'status']);
            $table->index(['user_id', 'attraction_id']);
            $table->index(['attraction_id', 'rating']);
            $table->index(['attraction_id', 'is_featured']);
            $table->index('helpful_votes');
            
            // Unique constraint - one review per user per attraction
            $table->unique(['attraction_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attraction_reviews');
    }
};
