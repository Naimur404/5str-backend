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
        Schema::table('views', function (Blueprint $table) {
            $table->decimal('user_latitude', 10, 8)->nullable()->after('viewable_id');
            $table->decimal('user_longitude', 11, 8)->nullable()->after('user_latitude');
            $table->string('user_area')->nullable()->after('user_longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('views', function (Blueprint $table) {
            $table->dropColumn(['user_latitude', 'user_longitude', 'user_area']);
        });
    }
};
