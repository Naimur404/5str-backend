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
        Schema::create('attractions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('type')->default('attraction'); // attraction, activity, spot
            $table->string('category')->nullable(); // nature, historical, adventure, cultural, etc.
            $table->string('subcategory')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address');
            $table->string('city');
            $table->string('area')->nullable();
            $table->string('district')->nullable();
            $table->string('country')->default('Bangladesh');
            $table->boolean('is_free')->default(true);
            $table->decimal('entry_fee', 10, 2)->nullable();
            $table->string('currency')->default('BDT');
            $table->json('opening_hours')->nullable();
            $table->json('contact_info')->nullable(); // phone, email, website
            $table->json('facilities')->nullable(); // parking, restroom, guide, etc.
            $table->json('best_time_to_visit')->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->string('difficulty_level')->nullable(); // easy, moderate, challenging
            $table->json('accessibility_info')->nullable();
            $table->decimal('overall_rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->integer('total_likes')->default(0);
            $table->integer('total_dislikes')->default(0);
            $table->integer('total_shares')->default(0);
            $table->integer('total_views')->default(0);
            $table->decimal('discovery_score', 5, 2)->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active'); // active, inactive, pending, rejected
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->json('meta_data')->nullable(); // additional custom fields
            $table->timestamps();

            // Indexes for better performance
            $table->index(['latitude', 'longitude']);
            $table->index(['city', 'area']);
            $table->index(['type', 'category']);
            $table->index(['is_active', 'status']);
            $table->index('overall_rating');
            $table->index('is_featured');
            $table->index('discovery_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attractions');
    }
};
