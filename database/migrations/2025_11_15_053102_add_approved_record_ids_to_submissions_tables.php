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
            $table->unsignedBigInteger('approved_business_id')->nullable()->after('admin_notes');
            $table->foreign('approved_business_id')->references('id')->on('businesses')->onDelete('set null');
        });
        
        Schema::table('attraction_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_attraction_id')->nullable()->after('admin_notes');
            $table->foreign('approved_attraction_id')->references('id')->on('attractions')->onDelete('set null');
        });
        
        Schema::table('offering_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_offering_id')->nullable()->after('admin_notes');
            $table->foreign('approved_offering_id')->references('id')->on('business_offerings')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_submissions', function (Blueprint $table) {
            $table->dropForeign(['approved_business_id']);
            $table->dropColumn('approved_business_id');
        });
        
        Schema::table('attraction_submissions', function (Blueprint $table) {
            $table->dropForeign(['approved_attraction_id']);
            $table->dropColumn('approved_attraction_id');
        });
        
        Schema::table('offering_submissions', function (Blueprint $table) {
            $table->dropForeign(['approved_offering_id']);
            $table->dropColumn('approved_offering_id');
        });
    }
};
