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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->enum('offer_type', ['percentage', 'fixed_amount', 'bogo', 'combo']);
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('minimum_spend', 10, 2)->nullable();
            $table->string('offer_code')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('current_usage')->default(0);
            $table->datetime('valid_from');
            $table->datetime('valid_to');
            $table->json('applicable_days')->nullable(); // [1,2,3,4,5,6,7]
            $table->string('banner_image')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['business_id', 'is_active']);
            $table->index(['valid_from', 'valid_to']);
            $table->index(['is_featured', 'is_active']);
            $table->index('offer_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
