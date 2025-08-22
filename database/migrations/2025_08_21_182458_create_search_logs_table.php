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
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // can be anonymous
            $table->string('search_term')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('user_latitude', 10, 8)->nullable();
            $table->decimal('user_longitude', 11, 8)->nullable();
            $table->json('filters_applied')->nullable();
            $table->integer('results_count');
            $table->foreignId('clicked_business_id')->nullable()->constrained('businesses')->onDelete('set null');
            $table->timestamps();
            
            // Indexes
            $table->index(['search_term', 'created_at']);
            $table->index(['category_id', 'created_at']);
            $table->index(['user_latitude', 'user_longitude']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
