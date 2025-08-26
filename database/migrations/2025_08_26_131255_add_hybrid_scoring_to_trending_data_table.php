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
        Schema::table('trending_data', function (Blueprint $table) {
            $table->decimal('hybrid_score', 8, 2)->default(0)->after('trend_score');
            $table->integer('view_count')->default(0)->after('hybrid_score');
            $table->integer('search_count')->default(0)->after('view_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trending_data', function (Blueprint $table) {
            $table->dropColumn(['hybrid_score', 'view_count', 'search_count']);
        });
    }
};
