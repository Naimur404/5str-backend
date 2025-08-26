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
        Schema::table('categories', function (Blueprint $table) {
            // Make icon_image nullable since it's optional in the form
            $table->string('icon_image')->nullable()->change();
            
            // Add default value to level field to prevent the error
            $table->tinyInteger('level')->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Revert changes
            $table->string('icon_image')->nullable(false)->change();
            $table->tinyInteger('level')->default(null)->change();
        });
    }
};
