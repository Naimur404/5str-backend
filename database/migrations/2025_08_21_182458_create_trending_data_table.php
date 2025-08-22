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
        Schema::create('trending_data', function (Blueprint $table) {
            $table->id();
            $table->enum('item_type', ['business', 'category', 'search_term']);
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_name');
            $table->string('location_area')->nullable();
            $table->decimal('trend_score', 8, 2);
            $table->enum('time_period', ['daily', 'weekly', 'monthly']);
            $table->date('date_period');
            $table->timestamps();
            
            // Indexes
            $table->index(['item_type', 'item_id']);
            $table->index(['trend_score', 'time_period']);
            $table->index(['location_area', 'time_period']);
            $table->index('date_period');
            
            // Composite unique index to prevent duplicate trend records
            $table->unique(['item_type', 'item_id', 'time_period', 'date_period', 'location_area'], 'trending_unique_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trending_data');
    }
};
