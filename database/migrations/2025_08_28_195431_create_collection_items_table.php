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
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('user_collections')->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable(); // User's personal note about this business
            $table->integer('sort_order')->default(0); // For custom ordering
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();
            
            // Ensure one business per collection (no duplicates)
            $table->unique(['collection_id', 'business_id']);
            
            // Indexes for better performance
            $table->index(['collection_id', 'sort_order']);
            $table->index('business_id');
            $table->index('added_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_items');
    }
};
