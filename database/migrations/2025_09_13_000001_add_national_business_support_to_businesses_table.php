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
            // Add fields for national businesses
            $table->boolean('is_national')->default(false)->after('is_active');
            $table->enum('service_coverage', ['local', 'regional', 'national'])->default('local')->after('is_national');
            $table->text('service_areas')->nullable()->after('service_coverage'); // JSON array of districts/divisions they serve
            $table->enum('business_model', ['physical_location', 'delivery_only', 'online_service', 'manufacturing', 'brand'])->default('physical_location')->after('service_areas');
            
            // Add indexes for better query performance
            $table->index(['is_national', 'is_active']);
            $table->index(['service_coverage', 'is_active']);
            $table->index(['business_model', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['business_model', 'is_active']);
            $table->dropIndex(['service_coverage', 'is_active']);
            $table->dropIndex(['is_national', 'is_active']);
            
            $table->dropColumn([
                'is_national',
                'service_coverage',
                'service_areas',
                'business_model'
            ]);
        });
    }
};