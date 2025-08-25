<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixTrendingDataEnum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:trending-enum';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix trending_data item_type enum to include offering';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking trending_data table enum...');
        
        try {
            // Check current enum values
            $result = DB::select("SHOW COLUMNS FROM trending_data LIKE 'item_type'");
            
            if (empty($result)) {
                $this->error('trending_data table or item_type column not found!');
                return Command::FAILURE;
            }
            
            $enumValues = $result[0]->Type ?? '';
            $this->line("Current enum: {$enumValues}");
            
            // Check if 'offering' is already in the enum
            if (str_contains($enumValues, 'offering')) {
                $this->info("✅ 'offering' already exists in item_type enum - no changes needed");
                return Command::SUCCESS;
            }
            
            // Add 'offering' to the enum
            $this->warn("Adding 'offering' to item_type enum...");
            
            DB::statement("ALTER TABLE trending_data MODIFY COLUMN item_type ENUM('business', 'category', 'search_term', 'offering')");
            
            $this->info("✅ Successfully added 'offering' to item_type enum!");
            
            // Verify the change
            $result = DB::select("SHOW COLUMNS FROM trending_data LIKE 'item_type'");
            $newEnumValues = $result[0]->Type ?? '';
            $this->line("Updated enum: {$newEnumValues}");
            
            // Test the analytics command
            $this->info("Testing analytics command...");
            $exitCode = $this->call('analytics:calculate-trending', ['period' => 'daily']);
            
            if ($exitCode === 0) {
                $this->info("✅ Analytics command working properly!");
            } else {
                $this->error("❌ Analytics command still has issues");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
