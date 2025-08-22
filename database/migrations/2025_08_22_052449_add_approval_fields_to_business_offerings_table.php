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
        Schema::table('business_offerings', function (Blueprint $table) {
            // Approval workflow fields
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->after('is_available');
            $table->text('rejection_reason')->nullable()->after('approval_status');
            $table->unsignedBigInteger('approved_by')->nullable()->after('rejection_reason');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            
            // Track pending changes for approval
            $table->json('pending_changes')->nullable()->after('approved_at');
            $table->boolean('has_pending_changes')->default(false)->after('pending_changes');
            
            // Foreign key for who approved
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            
            // Index for queries
            $table->index(['approval_status', 'has_pending_changes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_offerings', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['approval_status', 'has_pending_changes']);
            $table->dropColumn([
                'approval_status',
                'rejection_reason',
                'approved_by',
                'approved_at',
                'pending_changes',
                'has_pending_changes'
            ]);
        });
    }
};
