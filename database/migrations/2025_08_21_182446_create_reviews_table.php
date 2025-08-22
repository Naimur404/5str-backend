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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('reviewable_id');
            $table->string('reviewable_type'); // business, offering
            $table->tinyInteger('overall_rating'); // 1-5
            $table->tinyInteger('service_rating')->nullable(); // 1-5
            $table->tinyInteger('quality_rating')->nullable(); // 1-5
            $table->tinyInteger('value_rating')->nullable(); // 1-5
            $table->string('title')->nullable();
            $table->text('review_text');
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->date('visit_date')->nullable();
            $table->decimal('amount_spent', 10, 2)->nullable();
            $table->integer('party_size')->nullable();
            $table->boolean('is_recommended')->default(true);
            $table->boolean('is_verified_visit')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            
            // Indexes
            $table->index(['reviewable_id', 'reviewable_type']);
            $table->index(['user_id', 'reviewable_type']);
            $table->index(['overall_rating', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('helpful_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
