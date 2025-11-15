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
        Schema::create('offering_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('business_name');
            $table->text('business_address');
            $table->string('offering_name');
            $table->text('offering_description');
            $table->string('offering_category');
            $table->decimal('price', 10, 2)->nullable();
            $table->enum('price_type', ['fixed', 'range', 'negotiable', 'free'])->nullable();
            $table->string('availability')->nullable();
            $table->string('contact_info')->nullable();
            $table->json('images')->nullable();
            $table->text('additional_info')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('submission_type')->default('offering');
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
        Schema::dropIfExists('offering_submissions');
    }
};
