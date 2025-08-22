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
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image_url');
            $table->enum('link_type', ['category', 'business', 'offer', 'external']);
            $table->unsignedBigInteger('link_id')->nullable();
            $table->string('link_url')->nullable();
            $table->enum('position', ['hero', 'top', 'bottom']);
            $table->json('target_location')->nullable(); // specific cities/areas
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->integer('click_count')->default(0);
            $table->integer('view_count')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active', 'position']);
            $table->index(['start_date', 'end_date']);
            $table->index(['link_type', 'link_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
