<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'submission' to the existing point_type enum
        DB::statement("ALTER TABLE user_points MODIFY COLUMN point_type ENUM('review', 'helpful_vote', 'referral', 'submission')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'submission' from the point_type enum
        DB::statement("ALTER TABLE user_points MODIFY COLUMN point_type ENUM('review', 'helpful_vote', 'referral')");
    }
};
