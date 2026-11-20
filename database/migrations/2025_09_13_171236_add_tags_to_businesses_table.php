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
        Schema::table('businesses', function (Blueprint $table) {
            $table->json('product_tags')->nullable()->after('business_model');
            $table->json('business_tags')->nullable()->after('product_tags');
            
            // Add index for JSON searches
            $table->index('is_national');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the index up() added; left behind, it blocks the earlier
        // migration from dropping is_national on SQLite. Guarded because
        // some existing databases never got this index.
        $hasIsNationalIndex = Schema::hasIndex('businesses', ['is_national']);

        Schema::table('businesses', function (Blueprint $table) use ($hasIsNationalIndex) {
            if ($hasIsNationalIndex) {
                $table->dropIndex(['is_national']);
            }
            $table->dropColumn(['product_tags', 'business_tags']);
        });
    }
};
