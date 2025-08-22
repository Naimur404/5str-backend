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
        Schema::create('views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('viewable_id');
            $table->string('viewable_type');
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['viewable_id', 'viewable_type']);
            $table->index(['user_id', 'viewable_type']);
            $table->index('ip_address');
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('views');
    }
};
