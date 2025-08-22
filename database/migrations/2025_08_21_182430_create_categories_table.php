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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->tinyInteger('level'); // 1=main, 2=sub, 3=sub-sub
            $table->string('icon_image');
            $table->string('banner_image')->nullable();
            $table->text('description')->nullable();
            $table->string('color_code', 7)->nullable(); // hex color code
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('total_businesses')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['parent_id', 'level']);
            $table->index(['is_featured', 'is_active']);
            $table->index(['is_popular', 'is_active']);
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
