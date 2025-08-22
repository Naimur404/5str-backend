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
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('favoritable_id');
            $table->string('favoritable_type'); // business, offering, offer
            $table->timestamps();
            
            // Unique constraint to prevent duplicate favorites
            $table->unique(['user_id', 'favoritable_id', 'favoritable_type']);
            $table->index(['favoritable_id', 'favoritable_type']);
            $table->index(['user_id', 'favoritable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
