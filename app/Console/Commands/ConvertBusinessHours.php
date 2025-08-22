<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;

class ConvertBusinessHours extends Command
{
    protected $signature = 'business:convert-hours';
    protected $description = 'Convert business hours from object format to string format';

    public function handle()
    {
        $this->info('Converting business hours data format...');
        
        $businesses = Business::whereNotNull('opening_hours')->get();
        $converted = 0;
        
        foreach ($businesses as $business) {
            $hours = $business->opening_hours;
            $newHours = [];
            $needsConversion = false;
            
            if (is_array($hours)) {
                foreach ($hours as $day => $time) {
                    if (is_array($time) && isset($time['open']) && isset($time['close'])) {
                        // Convert from object format to string format
                        $openTime = $this->convertTime($time['open']);
                        $closeTime = $this->convertTime($time['close']);
                        $newHours[$day] = "$openTime - $closeTime";
                        $needsConversion = true;
                    } else {
                        // Already in string format
                        $newHours[$day] = $time;
                    }
                }
                
                if ($needsConversion) {
                    $business->update(['opening_hours' => $newHours]);
                    $converted++;
                    $this->line("Converted hours for: {$business->business_name}");
                }
            }
        }
        
        $this->info("Conversion complete! Updated $converted businesses.");
        return Command::SUCCESS;
    }
    
    private function convertTime($time)
    {
        // Convert 24-hour format to 12-hour format
        $dateTime = \DateTime::createFromFormat('H:i', $time);
        if ($dateTime) {
            return $dateTime->format('g:i A');
        }
        return $time; // Return as-is if conversion fails
    }
}
