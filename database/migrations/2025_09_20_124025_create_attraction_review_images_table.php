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
        Schema::create('attraction_review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('attraction_reviews')->onDelete('cascade');
            $table->string('image_path');
            $table->string('image_url')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size')->nullable(); // in bytes
            $table->json('image_dimensions')->nullable(); // width, height
            $table->string('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Indexes
            $table->index(['review_id', 'sort_order']);
            $table->index(['review_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attraction_review_images');
    }
};
