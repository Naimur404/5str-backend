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
        Schema::table('business_submissions', function (Blueprint $table) {
            $table->string('city')->nullable()->after('address');
        });
        
        Schema::table('attraction_submissions', function (Blueprint $table) {
            $table->string('city')->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_submissions', function (Blueprint $table) {
            $table->dropColumn('city');
        });
        
        Schema::table('attraction_submissions', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};
