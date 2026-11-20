<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('notifications');
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op. up() removes a legacy custom notifications
     * table that no migration in this repository creates, so recreating it
     * here left an orphan table behind after a full rollback, and the next
     * migrate failed on "table notifications already exists" (it is
     * recreated, in Laravel's format, by 2025_08_23_063446).
     */
    public function down(): void
    {
        //
    }
};
