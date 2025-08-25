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
        // Add 'offering' to the item_type enum
        DB::statement("ALTER TABLE trending_data MODIFY COLUMN item_type ENUM('business', 'category', 'search_term', 'offering')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'offering' from the item_type enum
        DB::statement("ALTER TABLE trending_data MODIFY COLUMN item_type ENUM('business', 'category', 'search_term')");
    }
};
