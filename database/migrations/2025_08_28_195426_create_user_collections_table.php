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
        Schema::create('user_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('cover_image')->nullable();
            $table->string('color_code')->default('#2563eb'); // Default blue color
            $table->integer('business_count')->default(0);
            $table->integer('follower_count')->default(0);
            $table->json('tags')->nullable(); // For categorizing collections
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'is_public']);
            $table->index('is_public');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_collections');
    }
};
