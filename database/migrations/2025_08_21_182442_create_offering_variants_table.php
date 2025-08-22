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
        Schema::create('offering_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained('business_offerings')->onDelete('cascade');
            $table->string('variant_name'); // size, color, type
            $table->string('variant_value'); // Large, Red, Premium
            $table->decimal('price_adjustment', 10, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['offering_id', 'variant_name']);
            $table->index(['offering_id', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offering_variants');
    }
};
