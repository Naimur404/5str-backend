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
            $table->dropColumn(['service_rating', 'quality_rating', 'value_rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->tinyInteger('service_rating')->nullable()->after('overall_rating');
            $table->tinyInteger('quality_rating')->nullable()->after('service_rating');
            $table->tinyInteger('value_rating')->nullable()->after('quality_rating');
        });
    }
};
