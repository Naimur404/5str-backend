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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('business_email')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('website_url')->nullable();
            $table->text('full_address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('city');
            $table->string('area');
            $table->string('landmark')->nullable();
            $table->json('opening_hours')->nullable();
            $table->tinyInteger('price_range')->default(1); // 1-4
            $table->boolean('has_delivery')->default(false);
            $table->boolean('has_pickup')->default(false);
            $table->boolean('has_parking')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('overall_rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->decimal('discovery_score', 5, 2)->default(0);
            $table->timestamps();
            
            // Indexes for location-based queries
            $table->index(['latitude', 'longitude']);
            $table->index(['city', 'area']);
            $table->index(['category_id', 'is_active']);
            $table->index(['overall_rating', 'is_active']);
            $table->index(['discovery_score', 'is_active']);
            $table->index(['is_featured', 'is_active']);
            $table->index('owner_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
