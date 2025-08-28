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
        Schema::create('collection_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('user_collections')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('followed_at')->useCurrent();
            $table->timestamps();
            
            // Ensure one follow per user per collection
            $table->unique(['collection_id', 'user_id']);
            
            // Indexes for better performance
            $table->index(['collection_id', 'followed_at']);
            $table->index(['user_id', 'followed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_followers');
    }
};
