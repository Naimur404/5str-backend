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
        // Check current enum values
        $result = DB::select("SHOW COLUMNS FROM trending_data LIKE 'item_type'");
        $enumValues = $result[0]->Type ?? '';
        
        // If 'offering' is not in the enum, add it
        if (!str_contains($enumValues, 'offering')) {
            DB::statement("ALTER TABLE trending_data MODIFY COLUMN item_type ENUM('business', 'category', 'search_term', 'offering')");
            echo "Added 'offering' to item_type enum\n";
        } else {
            echo "'offering' already exists in item_type enum\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'offering' from the enum
        DB::statement("ALTER TABLE trending_data MODIFY COLUMN item_type ENUM('business', 'category', 'search_term')");
    }
};
