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
        Schema::create('business_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('offering_type', ['product', 'service']);
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('price_max', 10, 2)->nullable(); // for range pricing
            $table->string('currency', 3)->default('BDT');
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['business_id', 'offering_type']);
            $table->index(['business_id', 'is_available']);
            $table->index(['category_id', 'offering_type']);
            $table->index(['is_popular', 'is_available']);
            $table->index(['is_featured', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_offerings');
    }
};
