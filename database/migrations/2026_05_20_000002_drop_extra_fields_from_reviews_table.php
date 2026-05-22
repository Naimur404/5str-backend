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
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn([
                'review_text',
                'pros',
                'cons',
                'visit_date',
                'amount_spent',
                'party_size',
                'is_verified_visit'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('review_text');
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->date('visit_date')->nullable();
            $table->decimal('amount_spent', 10, 2)->nullable();
            $table->integer('party_size')->nullable();
            $table->boolean('is_verified_visit')->default(false);
        });
    }
};
