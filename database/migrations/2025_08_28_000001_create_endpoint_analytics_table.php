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
        Schema::create('endpoint_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint'); // home_index, popular_nearby, top_rated, etc.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_area')->nullable(); // Specific ward/area like "Dhanmondi Ward 2"
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('additional_data')->nullable(); // Extra analytics data
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['endpoint', 'created_at']);
            $table->index('user_area');
            $table->index(['latitude', 'longitude']);
            $table->index('created_at');
            $table->index('user_id');
            
            // Foreign key constraint (optional, can be nullable for guests)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('endpoint_analytics');
    }
};
