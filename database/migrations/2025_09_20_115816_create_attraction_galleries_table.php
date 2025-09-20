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
        Schema::create('attraction_galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attraction_id')->constrained('attractions')->onDelete('cascade');
            $table->string('image_url');
            $table->string('image_path')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('alt_text')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('image_type')->default('gallery'); // cover, gallery, thumbnail
            $table->json('meta_data')->nullable(); // file size, dimensions, etc.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['attraction_id', 'is_active']);
            $table->index(['attraction_id', 'is_cover']);
            $table->index(['attraction_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attraction_galleries');
    }
};
