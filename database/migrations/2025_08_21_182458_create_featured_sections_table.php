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
        Schema::create('featured_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_name'); // top_services, popular_nearby, top_pizza, special_offers
            $table->string('title');
            $table->json('category_filter')->nullable();
            $table->integer('display_limit')->default(10);
            $table->enum('sort_criteria', ['rating', 'distance', 'popularity']);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->unique('section_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('featured_sections');
    }
};
