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
        Schema::table('offering_submissions', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('user_id')->constrained('businesses')->onDelete('set null');
            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offering_submissions', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropColumn('business_id');
        });
    }
};
