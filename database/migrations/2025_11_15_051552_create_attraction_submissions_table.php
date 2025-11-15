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
        Schema::create('attraction_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->enum('type', ['historical', 'natural', 'cultural', 'entertainment', 'religious', 'educational', 'recreational']);
            $table->text('address');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('entry_fee', 8, 2)->nullable();
            $table->json('visiting_hours')->nullable();
            $table->string('best_time_to_visit')->nullable();
            $table->json('facilities')->nullable();
            $table->json('images')->nullable();
            $table->text('additional_info')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('submission_type')->default('attraction');
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attraction_submissions');
    }
};
