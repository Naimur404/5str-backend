<?php

namespace App\Services;

class LocationService
{
    /**
     * Determine user's specific area/ward from coordinates with enhanced precision for full Bangladesh
     * Covers all 8 divisions, 64 districts, and major cities with ward-level precision
     */
    public function determineUserAreaPrecise($latitude, $longitude)
    {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=13&addressdetails=1";
    
    $context = stream_context_create([
        'http' => [
            'header' => 'User-Agent: YourAppName/1.0',
            'timeout' => 10
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    $data = json_decode($response, true);
    
    if (isset($data['address'])) {
        $address = $data['address'];
        
        // Priority order for location name selection
        $locationName = null;
        
        // First priority: Suburb/Neighbourhood (most specific)
        if (!empty($address['suburb'])) {
            $locationName = $address['suburb'];
        } elseif (!empty($address['neighbourhood'])) {
            $locationName = $address['neighbourhood'];
        }
        // Second priority: Road/Area name for major roads
        elseif (!empty($address['road']) && !empty($address['city'])) {
            $locationName = $address['road'];
        }
        // Third priority: City/Town/Municipality
        elseif (!empty($address['city'])) {
            $locationName = $address['city'];
        } elseif (!empty($address['town'])) {
            $locationName = $address['town'];
        } elseif (!empty($address['municipality'])) {
            $locationName = $address['municipality'];
        }
        // Fourth priority: District/County
        elseif (!empty($address['county'])) {
            $locationName = $address['county'];
        } elseif (!empty($address['state_district'])) {
            $locationName = $address['state_district'];
        }
        // Last priority: State/Division
        elseif (!empty($address['state'])) {
            $locationName = $address['state'];
        }
        
        // Clean up and format the name
        if ($locationName) {
            // Add city context if we have suburb/neighbourhood
            if (($address['suburb'] || $address['neighbourhood']) && !empty($address['city'])) {
                return $locationName . ', ' . $address['city'];
            }
            // Add district context if we have city but no district in name
            elseif (!empty($address['city']) && !empty($address['state_district']) && 
                    !stripos($locationName, $address['state_district'])) {
                return $locationName . ', ' . $address['state_district'];
            }
            else {
                return $locationName;
            }
        }
    }
    
        return 'Bangladesh';
    }

    // /**
    //  * Get area statistics and insights
    //  */
    public function getAreaInsights($latitude, $longitude)
    {
        $area = $this->determineUserAreaPrecise($latitude, $longitude);
        
        if (!$area) {
            return null;
        }

        // // Determine division
        // $division = $this->getDivisionFromArea($area);
        
        // // Determine district
        // $district = $this->getDistrictFromArea($area);
        
        return [
            'specific_area' => $area,
            'coordinates' => [
                'latitude' => $latitude,
                'longitude' => $longitude
            ],
            'precision_level' => $this->getPrecisionLevel($area)
        ];
    }

    // /**
    //  * Get division from area name
    //  */
    // private function getDivisionFromArea($area)
    // {
    //     if (str_contains($area, 'Dhaka') || str_contains($area, 'Dhanmondi') || 
    //         str_contains($area, 'Gulshan') || str_contains($area, 'Uttara') || 
    //         str_contains($area, 'Mirpur') || str_contains($area, 'Banani') ||
    //         str_contains($area, 'Bashundhara') || str_contains($area, 'Tejgaon') ||
    //         str_contains($area, 'Mohammadpur') || str_contains($area, 'Wari') ||
    //         str_contains($area, 'Motijheel') || str_contains($area, 'Ramna') ||
    //         str_contains($area, 'Lalmatia') || str_contains($area, 'Farmgate') ||
    //         str_contains($area, 'Cantonment') || str_contains($area, 'Baridhara') ||
    //         str_contains($area, 'Savar') || str_contains($area, 'Gazipur') ||
    //         str_contains($area, 'Narayanganj') || str_contains($area, 'Keraniganj') ||
    //         str_contains($area, 'Manikganj') || str_contains($area, 'Tongi')) {
    //         return 'Dhaka Division';
    //     } elseif (str_contains($area, 'Chittagong') || str_contains($area, 'Cox') ||
    //              str_contains($area, 'Comilla') || str_contains($area, 'Feni') ||
    //              str_contains($area, 'Noakhali') || str_contains($area, 'Lakshmipur') ||
    //              str_contains($area, 'Chandpur') || str_contains($area, 'Brahmanbaria') ||
    //              str_contains($area, 'Rangamati') || str_contains($area, 'Khagrachhari') ||
    //              str_contains($area, 'Bandarban')) {
    //         return 'Chittagong Division';
    //     } elseif (str_contains($area, 'Sylhet') || str_contains($area, 'Moulvibazar') ||
    //              str_contains($area, 'Habiganj') || str_contains($area, 'Sunamganj') ||
    //              str_contains($area, 'Sreemangal')) {
    //         return 'Sylhet Division';
    //     } elseif (str_contains($area, 'Rajshahi') || str_contains($area, 'Naogaon') ||
    //              str_contains($area, 'Pabna') || str_contains($area, 'Bogura') ||
    //              str_contains($area, 'Sirajganj') || str_contains($area, 'Natore') ||
    //              str_contains($area, 'Joypurhat') || str_contains($area, 'Chapainawabganj')) {
    //         return 'Rajshahi Division';
    //     } elseif (str_contains($area, 'Khulna') || str_contains($area, 'Jessore') ||
    //              str_contains($area, 'Satkhira') || str_contains($area, 'Narail') ||
    //              str_contains($area, 'Bagerhat') || str_contains($area, 'Chuadanga') ||
    //              str_contains($area, 'Kushtia') || str_contains($area, 'Meherpur') ||
    //              str_contains($area, 'Magura') || str_contains($area, 'Jhenaidah')) {
    //         return 'Khulna Division';
    //     } elseif (str_contains($area, 'Barisal') || str_contains($area, 'Patuakhali') ||
    //              str_contains($area, 'Pirojpur') || str_contains($area, 'Jhalokati') ||
    //              str_contains($area, 'Barguna') || str_contains($area, 'Bhola') ||
    //              str_contains($area, 'Kuakata')) {
    //         return 'Barisal Division';
    //     } elseif (str_contains($area, 'Rangpur') || str_contains($area, 'Dinajpur') ||
    //              str_contains($area, 'Gaibandha') || str_contains($area, 'Kurigram') ||
    //              str_contains($area, 'Lalmonirhat') || str_contains($area, 'Nilphamari') ||
    //              str_contains($area, 'Thakurgaon') || str_contains($area, 'Panchagarh') ||
    //              str_contains($area, 'Saidpur')) {
    //         return 'Rangpur Division';
    //     } elseif (str_contains($area, 'Mymensingh') || str_contains($area, 'Jamalpur') ||
    //              str_contains($area, 'Sherpur') || str_contains($area, 'Netrokona')) {
    //         return 'Mymensingh Division';
    //     } else {
    //         return 'Unknown Division';
    //     }
    // }

    // /**
    //  * Get district from area name
    //  */
    // private function getDistrictFromArea($area)
    // {
    //     // Extract district information from area name
    //     $districts = [
    //         'Dhaka', 'Gazipur', 'Narayanganj', 'Manikganj', 'Chittagong', 'Cox\'s Bazar', 
    //         'Comilla', 'Feni', 'Noakhali', 'Lakshmipur', 'Chandpur', 'Brahmanbaria',
    //         'Rangamati', 'Khagrachhari', 'Bandarban', 'Sylhet', 'Moulvibazar', 'Habiganj',
    //         'Sunamganj', 'Rajshahi', 'Naogaon', 'Pabna', 'Bogura', 'Sirajganj', 'Natore',
    //         'Joypurhat', 'Chapainawabganj', 'Khulna', 'Jessore', 'Satkhira', 'Narail',
    //         'Bagerhat', 'Chuadanga', 'Kushtia', 'Meherpur', 'Magura', 'Jhenaidah',
    //         'Barisal', 'Patuakhali', 'Pirojpur', 'Jhalokati', 'Barguna', 'Bhola',
    //         'Rangpur', 'Dinajpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari',
    //         'Thakurgaon', 'Panchagarh', 'Mymensingh', 'Jamalpur', 'Sherpur', 'Netrokona'
    //     ];

    //     foreach ($districts as $district) {
    //         if (str_contains($area, $district)) {
    //             return $district;
    //         }
    //     }

    //     return 'Unknown District';
    // }

    // /**
    //  * Get precision level of area detection
    //  */
    private function getPrecisionLevel($area)
    {
        if (str_contains($area, 'Ward') || str_contains($area, 'Sector') || 
            str_contains($area, 'Block') || str_contains($area, 'Circle')) {
            return 'Ward Level';
        } elseif (str_contains($area, 'Upazila') || str_contains($area, 'Sadar')) {
            return 'Upazila Level';
        } elseif (str_contains($area, 'District') || str_contains($area, 'Division')) {
            return 'District/Division Level';
        } else {
            return 'City/Area Level';
        }
    }

    // /**
    //  * Check if coordinates are within Bangladesh boundaries
    //  */
    public function isWithinBangladesh($latitude, $longitude)
    {
        $lat = (float) $latitude;
        $lng = (float) $longitude;
        
        // Bangladesh approximate boundaries
        return $lat >= 20.5000 && $lat <= 26.6000 && $lng >= 88.0000 && $lng <= 92.8000;
    }

    // /**
    //  * Check Dhaka Metropolitan and surrounding areas with ward-level precision
    //  */
    // private function checkDhakaMetropolitanAreas($lat, $lng)
    // {
    //     // Enhanced Dhaka City Areas with ward-level precision
    //     $dhakaAreas = [
    //         // Dhanmondi with specific ward boundaries
    //         ['name' => 'Dhanmondi Ward 2', 'lat_min' => 23.7400, 'lat_max' => 23.7550, 'lng_min' => 90.3650, 'lng_max' => 90.3800],
    //         ['name' => 'Dhanmondi Ward 5', 'lat_min' => 23.7350, 'lat_max' => 23.7450, 'lng_min' => 90.3700, 'lng_max' => 90.3850],
    //         ['name' => 'Dhanmondi Ward 9', 'lat_min' => 23.7450, 'lat_max' => 23.7600, 'lng_min' => 90.3750, 'lng_max' => 90.3900],
    //         ['name' => 'Dhanmondi Ward 27', 'lat_min' => 23.7500, 'lat_max' => 23.7650, 'lng_min' => 90.3600, 'lng_max' => 90.3750],
    //         ['name' => 'Dhanmondi Ward 32', 'lat_min' => 23.7250, 'lat_max' => 23.7400, 'lng_min' => 90.3700, 'lng_max' => 90.3850],
            
    //         // Gulshan with specific blocks/circles
    //         ['name' => 'Gulshan 1', 'lat_min' => 23.7780, 'lat_max' => 23.7850, 'lng_min' => 90.4100, 'lng_max' => 90.4200],
    //         ['name' => 'Gulshan 2', 'lat_min' => 23.7850, 'lat_max' => 23.7950, 'lng_min' => 90.4150, 'lng_max' => 90.4250],
    //         ['name' => 'Gulshan Circle 1', 'lat_min' => 23.7800, 'lat_max' => 23.7900, 'lng_min' => 90.4050, 'lng_max' => 90.4150],
    //         ['name' => 'Gulshan Circle 2', 'lat_min' => 23.7900, 'lat_max' => 23.8000, 'lng_min' => 90.4100, 'lng_max' => 90.4200],
    //         ['name' => 'Gulshan Avenue', 'lat_min' => 23.7750, 'lat_max' => 23.7850, 'lng_min' => 90.4050, 'lng_max' => 90.4150],
            
    //         // Banani areas
    //         ['name' => 'Banani Commercial Area', 'lat_min' => 23.7900, 'lat_max' => 23.8000, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Banani Residential', 'lat_min' => 23.7950, 'lat_max' => 23.8050, 'lng_min' => 90.4050, 'lng_max' => 90.4150],
    //         ['name' => 'Banani DOHS', 'lat_min' => 23.7850, 'lat_max' => 23.7950, 'lng_min' => 90.3950, 'lng_max' => 90.4050],
            
    //         // Uttara sectors
    //         ['name' => 'Uttara Sector 1', 'lat_min' => 23.8750, 'lat_max' => 23.8850, 'lng_min' => 90.3850, 'lng_max' => 90.3950],
    //         ['name' => 'Uttara Sector 3', 'lat_min' => 23.8700, 'lat_max' => 23.8800, 'lng_min' => 90.3950, 'lng_max' => 90.4050],
    //         ['name' => 'Uttara Sector 4', 'lat_min' => 23.8750, 'lat_max' => 23.8850, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Uttara Sector 5', 'lat_min' => 23.8700, 'lat_max' => 23.8800, 'lng_min' => 90.4050, 'lng_max' => 90.4150],
    //         ['name' => 'Uttara Sector 6', 'lat_min' => 23.8650, 'lat_max' => 23.8750, 'lng_min' => 90.4050, 'lng_max' => 90.4150],
    //         ['name' => 'Uttara Sector 7', 'lat_min' => 23.8650, 'lat_max' => 23.8750, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Uttara Sector 8', 'lat_min' => 23.8600, 'lat_max' => 23.8700, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Uttara Sector 9', 'lat_min' => 23.8550, 'lat_max' => 23.8650, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Uttara Sector 10', 'lat_min' => 23.8600, 'lat_max' => 23.8700, 'lng_min' => 90.3900, 'lng_max' => 90.4000],
    //         ['name' => 'Uttara Sector 11', 'lat_min' => 23.8550, 'lat_max' => 23.8650, 'lng_min' => 90.3950, 'lng_max' => 90.4050],
    //         ['name' => 'Uttara Sector 12', 'lat_min' => 23.8550, 'lat_max' => 23.8650, 'lng_min' => 90.3950, 'lng_max' => 90.4050],
            
    //         // Mirpur areas with all sectors
    //         ['name' => 'Mirpur 1', 'lat_min' => 23.7950, 'lat_max' => 23.8050, 'lng_min' => 90.3500, 'lng_max' => 90.3600],
    //         ['name' => 'Mirpur 2', 'lat_min' => 23.8000, 'lat_max' => 23.8100, 'lng_min' => 90.3550, 'lng_max' => 90.3650],
    //         ['name' => 'Mirpur 6', 'lat_min' => 23.8100, 'lat_max' => 23.8200, 'lng_min' => 90.3600, 'lng_max' => 90.3700],
    //         ['name' => 'Mirpur 10', 'lat_min' => 23.8050, 'lat_max' => 23.8150, 'lng_min' => 90.3600, 'lng_max' => 90.3700],
    //         ['name' => 'Mirpur 11', 'lat_min' => 23.8000, 'lat_max' => 23.8100, 'lng_min' => 90.3650, 'lng_max' => 90.3750],
    //         ['name' => 'Mirpur 12', 'lat_min' => 23.7950, 'lat_max' => 23.8050, 'lng_min' => 90.3700, 'lng_max' => 90.3800],
    //         ['name' => 'Mirpur 13', 'lat_min' => 23.7900, 'lat_max' => 23.8000, 'lng_min' => 90.3650, 'lng_max' => 90.3750],
    //         ['name' => 'Mirpur 14', 'lat_min' => 23.7850, 'lat_max' => 23.7950, 'lng_min' => 90.3600, 'lng_max' => 90.3700],
    //         ['name' => 'Mirpur DOHS', 'lat_min' => 23.8150, 'lat_max' => 23.8250, 'lng_min' => 90.3650, 'lng_max' => 90.3750],
            
    //         // Old Dhaka specific areas with wards
    //         ['name' => 'Lalbagh Ward', 'lat_min' => 23.7150, 'lat_max' => 23.7250, 'lng_min' => 90.3800, 'lng_max' => 90.3900],
    //         ['name' => 'Chawk Bazaar', 'lat_min' => 23.7100, 'lat_max' => 23.7200, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Sadarghat', 'lat_min' => 23.7050, 'lat_max' => 23.7150, 'lng_min' => 90.4050, 'lng_max' => 90.4150],
    //         ['name' => 'Islampur', 'lat_min' => 23.7000, 'lat_max' => 23.7100, 'lng_min' => 90.4100, 'lng_max' => 90.4200],
    //         ['name' => 'Nawabpur', 'lat_min' => 23.7050, 'lat_max' => 23.7150, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Shakhari Bazaar', 'lat_min' => 23.7100, 'lat_max' => 23.7200, 'lng_min' => 90.4050, 'lng_max' => 90.4150],
    //         ['name' => 'Armanitola', 'lat_min' => 23.7150, 'lat_max' => 23.7250, 'lng_min' => 90.4100, 'lng_max' => 90.4200],
            
    //         // Wari areas
    //         ['name' => 'Wari Residential', 'lat_min' => 23.7200, 'lat_max' => 23.7300, 'lng_min' => 90.4100, 'lng_max' => 90.4200],
    //         ['name' => 'Wari Commercial', 'lat_min' => 23.7250, 'lat_max' => 23.7350, 'lng_min' => 90.4150, 'lng_max' => 90.4250],
    //         ['name' => 'Rankin Street', 'lat_min' => 23.7200, 'lat_max' => 23.7300, 'lng_min' => 90.4150, 'lng_max' => 90.4250],
            
    //         // Motijheel areas
    //         ['name' => 'Motijheel Commercial', 'lat_min' => 23.7300, 'lat_max' => 23.7400, 'lng_min' => 90.4150, 'lng_max' => 90.4250],
    //         ['name' => 'Dilkusha', 'lat_min' => 23.7250, 'lat_max' => 23.7350, 'lng_min' => 90.4200, 'lng_max' => 90.4300],
    //         ['name' => 'Purana Paltan', 'lat_min' => 23.7350, 'lat_max' => 23.7450, 'lng_min' => 90.4100, 'lng_max' => 90.4200],
    //         ['name' => 'Naya Paltan', 'lat_min' => 23.7400, 'lat_max' => 23.7500, 'lng_min' => 90.4150, 'lng_max' => 90.4250],
            
    //         // Tejgaon areas
    //         ['name' => 'Tejgaon Industrial', 'lat_min' => 23.7600, 'lat_max' => 23.7700, 'lng_min' => 90.3950, 'lng_max' => 90.4050],
    //         ['name' => 'Tejgaon Sadar', 'lat_min' => 23.7650, 'lat_max' => 23.7750, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Karwan Bazaar', 'lat_min' => 23.7500, 'lat_max' => 23.7600, 'lng_min' => 90.3900, 'lng_max' => 90.4000],
    //         ['name' => 'Farmgate', 'lat_min' => 23.7550, 'lat_max' => 23.7650, 'lng_min' => 90.3850, 'lng_max' => 90.3950],
            
    //         // Mohammadpur areas
    //         ['name' => 'Mohammadpur Housing', 'lat_min' => 23.7600, 'lat_max' => 23.7700, 'lng_min' => 90.3550, 'lng_max' => 90.3650],
    //         ['name' => 'Mohammadpur Krishi Market', 'lat_min' => 23.7650, 'lat_max' => 23.7750, 'lng_min' => 90.3600, 'lng_max' => 90.3700],
    //         ['name' => 'Shyamoli', 'lat_min' => 23.7750, 'lat_max' => 23.7850, 'lng_min' => 90.3600, 'lng_max' => 90.3700],
    //         ['name' => 'Adabor', 'lat_min' => 23.7700, 'lat_max' => 23.7800, 'lng_min' => 90.3500, 'lng_max' => 90.3600],
            
    //         // Bashundhara areas
    //         ['name' => 'Bashundhara Residential Area', 'lat_min' => 23.8100, 'lat_max' => 23.8300, 'lng_min' => 90.4250, 'lng_max' => 90.4450],
    //         ['name' => 'Bashundhara City Mall Area', 'lat_min' => 23.7500, 'lat_max' => 23.7600, 'lng_min' => 90.3900, 'lng_max' => 90.4000],
    //         ['name' => 'Bashundhara Block A', 'lat_min' => 23.8150, 'lat_max' => 23.8250, 'lng_min' => 90.4300, 'lng_max' => 90.4400],
    //         ['name' => 'Bashundhara Block B', 'lat_min' => 23.8100, 'lat_max' => 23.8200, 'lng_min' => 90.4350, 'lng_max' => 90.4450],
            
    //         // Other important Dhaka areas
    //         ['name' => 'Cantonment', 'lat_min' => 23.7800, 'lat_max' => 23.8000, 'lng_min' => 90.3800, 'lng_max' => 90.4000],
    //         ['name' => 'Baridhara', 'lat_min' => 23.8000, 'lat_max' => 23.8200, 'lng_min' => 90.4200, 'lng_max' => 90.4400],
    //         ['name' => 'Baridhara DOHS', 'lat_min' => 23.8050, 'lat_max' => 23.8150, 'lng_min' => 90.4250, 'lng_max' => 90.4350],
    //         ['name' => 'Ramna', 'lat_min' => 23.7350, 'lat_max' => 23.7450, 'lng_min' => 90.4000, 'lng_max' => 90.4100],
    //         ['name' => 'Segunbagicha', 'lat_min' => 23.7400, 'lat_max' => 23.7500, 'lng_min' => 90.4050, 'lng_max' => 90.4150],
    //         ['name' => 'Green Road', 'lat_min' => 23.7450, 'lat_max' => 23.7550, 'lng_min' => 90.3750, 'lng_max' => 90.3850],
    //         ['name' => 'Elephant Road', 'lat_min' => 23.7350, 'lat_max' => 23.7450, 'lng_min' => 90.3800, 'lng_max' => 90.3900],
    //         ['name' => 'Lalmatia', 'lat_min' => 23.7650, 'lat_max' => 23.7750, 'lng_min' => 90.3650, 'lng_max' => 90.3750],
    //         ['name' => 'Kalabagan', 'lat_min' => 23.7350, 'lat_max' => 23.7450, 'lng_min' => 90.3750, 'lng_max' => 90.3850],
    //         ['name' => 'Hazaribagh', 'lat_min' => 23.7250, 'lat_max' => 23.7350, 'lng_min' => 90.3600, 'lng_max' => 90.3700],
    //         ['name' => 'Newmarket', 'lat_min' => 23.7300, 'lat_max' => 23.7400, 'lng_min' => 90.3850, 'lng_max' => 90.3950],
    //         ['name' => 'Azimpur', 'lat_min' => 23.7200, 'lat_max' => 23.7300, 'lng_min' => 90.3750, 'lng_max' => 90.3850],
            
    //         // Dhaka University Area
    //         ['name' => 'TSC Area', 'lat_min' => 23.7300, 'lat_max' => 23.7400, 'lng_min' => 90.3900, 'lng_max' => 90.4000],
    //         ['name' => 'Shahbagh', 'lat_min' => 23.7380, 'lat_max' => 23.7480, 'lng_min' => 90.3950, 'lng_max' => 90.4050],
            
    //         // Airport Area
    //         ['name' => 'Hazrat Shahjalal Airport', 'lat_min' => 23.8400, 'lat_max' => 23.8600, 'lng_min' => 90.3950, 'lng_max' => 90.4150],
            
    //         // Savar and surrounding areas
    //         ['name' => 'Savar Cantonment', 'lat_min' => 23.8400, 'lat_max' => 23.8600, 'lng_min' => 90.2500, 'lng_max' => 90.2700],
    //         ['name' => 'Savar Upazila', 'lat_min' => 23.8300, 'lat_max' => 23.8700, 'lng_min' => 90.2200, 'lng_max' => 90.2800],
            
    //         // Keraniganj areas
    //         ['name' => 'Keraniganj', 'lat_min' => 23.6800, 'lat_max' => 23.7200, 'lng_min' => 90.3500, 'lng_max' => 90.4000],
            
    //         // Narayanganj areas
    //         ['name' => 'Narayanganj Sadar', 'lat_min' => 23.6100, 'lat_max' => 23.6400, 'lng_min' => 90.4800, 'lng_max' => 90.5200],
    //         ['name' => 'Fatullah', 'lat_min' => 23.5900, 'lat_max' => 23.6200, 'lng_min' => 90.5000, 'lng_max' => 90.5300],
    //         ['name' => 'Siddhirganj', 'lat_min' => 23.6200, 'lat_max' => 23.6500, 'lng_min' => 90.4500, 'lng_max' => 90.4800],
            
    //         // Gazipur areas
    //         ['name' => 'Gazipur Sadar', 'lat_min' => 23.9800, 'lat_max' => 24.0200, 'lng_min' => 90.4000, 'lng_max' => 90.4400],
    //         ['name' => 'Tongi', 'lat_min' => 23.8800, 'lat_max' => 23.9200, 'lng_min' => 90.3800, 'lng_max' => 90.4200],
            
    //         // Manikganj areas
    //         ['name' => 'Manikganj Sadar', 'lat_min' => 23.8400, 'lat_max' => 23.8800, 'lng_min' => 90.0000, 'lng_max' => 90.0400],
    //     ];

    //     foreach ($dhakaAreas as $area) {
    //         if ($lat >= $area['lat_min'] && $lat <= $area['lat_max'] && 
    //             $lng >= $area['lng_min'] && $lng <= $area['lng_max']) {
    //             return $area['name'];
    //         }
    //     }

    //     // Broader Dhaka Metropolitan detection
    //     if ($lat >= 23.6000 && $lat <= 24.1000 && $lng >= 90.1000 && $lng <= 90.6000) {
    //         return 'Dhaka Metropolitan';
    //     }

    //     return null;
    // }

    // /**
    //  * Check Chittagong Division areas with comprehensive coverage
    //  */
    // private function checkChittagongAreas($lat, $lng)
    // {
    //     $chittagongAreas = [
    //         // Chittagong City Corporation areas with ward precision
    //         ['name' => 'Chittagong GEC Circle', 'lat_min' => 22.3550, 'lat_max' => 22.3650, 'lng_min' => 91.8250, 'lng_max' => 91.8350],
    //         ['name' => 'Chittagong Agrabad', 'lat_min' => 22.3300, 'lat_max' => 22.3400, 'lng_min' => 91.8100, 'lng_max' => 91.8200],
    //         ['name' => 'Chittagong Port Area', 'lat_min' => 22.3200, 'lat_max' => 22.3300, 'lng_min' => 91.8000, 'lng_max' => 91.8100],
    //         ['name' => 'Chittagong Nasirabad', 'lat_min' => 22.3650, 'lat_max' => 22.3750, 'lng_min' => 91.8200, 'lng_max' => 91.8300],
    //         ['name' => 'Chittagong Panchlaish', 'lat_min' => 22.3700, 'lat_max' => 22.3800, 'lng_min' => 91.8300, 'lng_max' => 91.8400],
    //         ['name' => 'Chittagong Khulshi', 'lat_min' => 22.3400, 'lat_max' => 22.3500, 'lng_min' => 91.8400, 'lng_max' => 91.8500],
    //         ['name' => 'Chittagong Halishahar', 'lat_min' => 22.3500, 'lat_max' => 22.3600, 'lng_min' => 91.8000, 'lng_max' => 91.8100],
    //         ['name' => 'Chittagong Chawk Bazaar', 'lat_min' => 22.3350, 'lat_max' => 22.3450, 'lng_min' => 91.8350, 'lng_max' => 91.8450],
    //         ['name' => 'Chittagong Kotwali', 'lat_min' => 22.3400, 'lat_max' => 22.3500, 'lng_min' => 91.8300, 'lng_max' => 91.8400],
    //         ['name' => 'Chittagong Double Mooring', 'lat_min' => 22.3100, 'lat_max' => 22.3200, 'lng_min' => 91.8200, 'lng_max' => 91.8300],
    //         ['name' => 'Chittagong EPZ', 'lat_min' => 22.2800, 'lat_max' => 22.2900, 'lng_min' => 91.8100, 'lng_max' => 91.8200],
    //         ['name' => 'Chittagong Oxygen', 'lat_min' => 22.3600, 'lat_max' => 22.3700, 'lng_min' => 91.8100, 'lng_max' => 91.8200],
    //         ['name' => 'Chittagong Pahartali', 'lat_min' => 22.3800, 'lat_max' => 22.3900, 'lng_min' => 91.8200, 'lng_max' => 91.8300],
    //         ['name' => 'Chittagong Bayezid', 'lat_min' => 22.3200, 'lat_max' => 22.3300, 'lng_min' => 91.8500, 'lng_max' => 91.8600],
    //         ['name' => 'Chittagong Chandgaon', 'lat_min' => 22.3400, 'lat_max' => 22.3500, 'lng_min' => 91.8600, 'lng_max' => 91.8700],
    //         ['name' => 'Chittagong Bakalia', 'lat_min' => 22.3450, 'lat_max' => 22.3550, 'lng_min' => 91.8150, 'lng_max' => 91.8250],
    //         ['name' => 'Chittagong Akbar Shah', 'lat_min' => 22.3250, 'lat_max' => 22.3350, 'lng_min' => 91.8450, 'lng_max' => 91.8550],
            
    //         // Cox's Bazar District
    //         ['name' => 'Cox\'s Bazar Sadar', 'lat_min' => 21.4200, 'lat_max' => 21.4600, 'lng_min' => 91.9500, 'lng_max' => 91.9900],
    //         ['name' => 'Cox\'s Bazar Beach', 'lat_min' => 21.4000, 'lat_max' => 21.4400, 'lng_min' => 91.9700, 'lng_max' => 92.0100],
    //         ['name' => 'Teknaf', 'lat_min' => 20.8500, 'lat_max' => 21.0500, 'lng_min' => 92.1500, 'lng_max' => 92.3500],
    //         ['name' => 'Ukhiya', 'lat_min' => 21.2000, 'lat_max' => 21.3000, 'lng_min' => 92.0500, 'lng_max' => 92.1500],
    //         ['name' => 'Ramu', 'lat_min' => 21.3500, 'lat_max' => 21.4500, 'lng_min' => 92.0000, 'lng_max' => 92.1000],
            
    //         // Comilla District
    //         ['name' => 'Comilla Sadar', 'lat_min' => 23.4500, 'lat_max' => 23.4700, 'lng_min' => 91.1700, 'lng_max' => 91.1900],
    //         ['name' => 'Comilla Cantonment', 'lat_min' => 23.4400, 'lat_max' => 23.4600, 'lng_min' => 91.1800, 'lng_max' => 91.2000],
    //         ['name' => 'Daudkandi', 'lat_min' => 23.4200, 'lat_max' => 23.4800, 'lng_min' => 90.9500, 'lng_max' => 91.0500],
    //         ['name' => 'Brahmanpara', 'lat_min' => 23.4000, 'lat_max' => 23.5000, 'lng_min' => 91.0500, 'lng_max' => 91.1500],
    //         ['name' => 'Chandina', 'lat_min' => 23.3500, 'lat_max' => 23.4500, 'lng_min' => 91.1000, 'lng_max' => 91.2000],
            
    //         // Feni District
    //         ['name' => 'Feni Sadar', 'lat_min' => 23.0000, 'lat_max' => 23.0200, 'lng_min' => 91.3800, 'lng_max' => 91.4000],
    //         ['name' => 'Chhagalnaiya', 'lat_min' => 22.9500, 'lat_max' => 23.0500, 'lng_min' => 91.5000, 'lng_max' => 91.6000],
    //         ['name' => 'Daganbhuiyan', 'lat_min' => 22.8500, 'lat_max' => 22.9500, 'lng_min' => 91.4500, 'lng_max' => 91.5500],
            
    //         // Noakhali District
    //         ['name' => 'Noakhali Sadar', 'lat_min' => 22.8500, 'lat_max' => 22.8700, 'lng_min' => 91.0900, 'lng_max' => 91.1100],
    //         ['name' => 'Maijdee', 'lat_min' => 22.8600, 'lat_max' => 22.8800, 'lng_min' => 91.1000, 'lng_max' => 91.1200],
    //         ['name' => 'Companiganj', 'lat_min' => 22.8000, 'lat_max' => 22.9000, 'lng_min' => 91.0000, 'lng_max' => 91.1000],
    //         ['name' => 'Begumganj', 'lat_min' => 22.7500, 'lat_max' => 22.8500, 'lng_min' => 91.0500, 'lng_max' => 91.1500],
            
    //         // Lakshmipur District
    //         ['name' => 'Lakshmipur Sadar', 'lat_min' => 22.9400, 'lat_max' => 22.9600, 'lng_min' => 90.8200, 'lng_max' => 90.8400],
    //         ['name' => 'Raipur', 'lat_min' => 22.9800, 'lat_max' => 23.0800, 'lng_min' => 90.7500, 'lng_max' => 90.8500],
    //         ['name' => 'Ramganj', 'lat_min' => 22.8500, 'lat_max' => 22.9500, 'lng_min' => 90.7500, 'lng_max' => 90.8500],
            
    //         // Chandpur District
    //         ['name' => 'Chandpur Sadar', 'lat_min' => 23.2300, 'lat_max' => 23.2500, 'lng_min' => 90.6700, 'lng_max' => 90.6900],
    //         ['name' => 'Faridganj', 'lat_min' => 23.1800, 'lat_max' => 23.2800, 'lng_min' => 90.6000, 'lng_max' => 90.7000],
    //         ['name' => 'Haimchar', 'lat_min' => 23.1500, 'lat_max' => 23.2500, 'lng_min' => 90.7000, 'lng_max' => 90.8000],
            
    //         // Brahmanbaria District
    //         ['name' => 'Brahmanbaria Sadar', 'lat_min' => 23.9500, 'lat_max' => 23.9700, 'lng_min' => 91.1100, 'lng_max' => 91.1300],
    //         ['name' => 'Ashuganj', 'lat_min' => 24.0000, 'lat_max' => 24.1000, 'lng_min' => 90.9500, 'lng_max' => 91.0500],
    //         ['name' => 'Kasba', 'lat_min' => 23.9000, 'lat_max' => 24.0000, 'lng_min' => 91.0500, 'lng_max' => 91.1500],
            
    //         // Rangamati District (Hill Tracts)
    //         ['name' => 'Rangamati Sadar', 'lat_min' => 22.6200, 'lat_max' => 22.6400, 'lng_min' => 92.1800, 'lng_max' => 92.2000],
    //         ['name' => 'Kaptai', 'lat_min' => 22.5000, 'lat_max' => 22.6000, 'lng_min' => 92.2000, 'lng_max' => 92.3000],
    //         ['name' => 'Baghaichhari', 'lat_min' => 22.5500, 'lat_max' => 22.6500, 'lng_min' => 92.2500, 'lng_max' => 92.3500],
            
    //         // Khagrachhari District
    //         ['name' => 'Khagrachhari Sadar', 'lat_min' => 23.1100, 'lat_max' => 23.1300, 'lng_min' => 91.9800, 'lng_max' => 92.0000],
    //         ['name' => 'Dighinala', 'lat_min' => 23.0500, 'lat_max' => 23.1500, 'lng_min' => 92.0500, 'lng_max' => 92.1500],
    //         ['name' => 'Panchhari', 'lat_min' => 23.2000, 'lat_max' => 23.3000, 'lng_min' => 92.0000, 'lng_max' => 92.1000],
            
    //         // Bandarban District
    //         ['name' => 'Bandarban Sadar', 'lat_min' => 22.1900, 'lat_max' => 22.2100, 'lng_min' => 92.2000, 'lng_max' => 92.2200],
    //         ['name' => 'Lama', 'lat_min' => 21.8000, 'lat_max' => 21.9000, 'lng_min' => 92.1500, 'lng_max' => 92.2500],
    //         ['name' => 'Naikhongchhari', 'lat_min' => 21.4000, 'lat_max' => 21.5000, 'lng_min' => 92.1000, 'lng_max' => 92.2000],
    //         ['name' => 'Thanchi', 'lat_min' => 21.8500, 'lat_max' => 22.1500, 'lng_min' => 92.2500, 'lng_max' => 92.5500],
    //     ];

    //     foreach ($chittagongAreas as $area) {
    //         if ($lat >= $area['lat_min'] && $lat <= $area['lat_max'] && 
    //             $lng >= $area['lng_min'] && $lng <= $area['lng_max']) {
    //             return $area['name'];
    //         }
    //     }

    //     // Broader Chittagong Division detection
    //     if ($lat >= 20.5000 && $lat <= 24.5000 && $lng >= 90.5000 && $lng <= 92.8000) {
    //         return 'Chittagong Division';
    //     }

    //     return null;
    // }

    // /**
    //  * Check Sylhet Division areas
    //  */
    // private function checkSylhetAreas($lat, $lng)
    // {
    //     $sylhetAreas = [
    //         // Sylhet City Corporation areas
    //         ['name' => 'Sylhet Bandar Bazaar', 'lat_min' => 24.8900, 'lat_max' => 24.9100, 'lng_min' => 91.8600, 'lng_max' => 91.8800],
    //         ['name' => 'Sylhet Zindabazar', 'lat_min' => 24.8800, 'lat_max' => 24.9000, 'lng_min' => 91.8700, 'lng_max' => 91.8900],
    //         ['name' => 'Sylhet Kotwali', 'lat_min' => 24.8950, 'lat_max' => 24.9150, 'lng_min' => 91.8650, 'lng_max' => 91.8850],
    //         ['name' => 'Sylhet Subhanighat', 'lat_min' => 24.8850, 'lat_max' => 24.9050, 'lng_min' => 91.8750, 'lng_max' => 91.8950],
    //         ['name' => 'Sylhet Shahporan', 'lat_min' => 24.8700, 'lat_max' => 24.8900, 'lng_min' => 91.8800, 'lng_max' => 91.9000],
    //         ['name' => 'Sylhet Dakshin Surma', 'lat_min' => 24.8600, 'lat_max' => 24.8800, 'lng_min' => 91.8500, 'lng_max' => 91.8700],
    //         ['name' => 'Sylhet Airport Area', 'lat_min' => 24.9500, 'lat_max' => 24.9700, 'lng_min' => 91.8800, 'lng_max' => 91.9000],
            
    //         // Moulvibazar District
    //         ['name' => 'Moulvibazar Sadar', 'lat_min' => 24.4800, 'lat_max' => 24.5000, 'lng_min' => 91.7700, 'lng_max' => 91.7900],
    //         ['name' => 'Sreemangal', 'lat_min' => 24.3000, 'lat_max' => 24.3200, 'lng_min' => 91.7200, 'lng_max' => 91.7400],
    //         ['name' => 'Kamalganj', 'lat_min' => 24.3500, 'lat_max' => 24.4500, 'lng_min' => 91.8000, 'lng_max' => 91.9000],
    //         ['name' => 'Kulaura', 'lat_min' => 24.4000, 'lat_max' => 24.5000, 'lng_min' => 91.9500, 'lng_max' => 92.0500],
    //         ['name' => 'Rajnagar', 'lat_min' => 24.5200, 'lat_max' => 24.6200, 'lng_min' => 91.6500, 'lng_max' => 91.7500],
    //         ['name' => 'Barlekha', 'lat_min' => 24.1500, 'lat_max' => 24.2500, 'lng_min' => 91.8500, 'lng_max' => 91.9500],
    //         ['name' => 'Juri', 'lat_min' => 24.2500, 'lat_max' => 24.3500, 'lng_min' => 91.6500, 'lng_max' => 91.7500],
            
    //         // Habiganj District
    //         ['name' => 'Habiganj Sadar', 'lat_min' => 24.3700, 'lat_max' => 24.3900, 'lng_min' => 91.4100, 'lng_max' => 91.4300],
    //         ['name' => 'Madhabpur', 'lat_min' => 24.1500, 'lat_max' => 24.2500, 'lng_min' => 91.2000, 'lng_max' => 91.3000],
    //         ['name' => 'Nabiganj', 'lat_min' => 24.5000, 'lat_max' => 24.6000, 'lng_min' => 91.3000, 'lng_max' => 91.4000],
    //         ['name' => 'Bahubal', 'lat_min' => 24.2000, 'lat_max' => 24.3000, 'lng_min' => 91.2500, 'lng_max' => 91.3500],
    //         ['name' => 'Ajmiriganj', 'lat_min' => 24.2500, 'lat_max' => 24.3500, 'lng_min' => 91.4500, 'lng_max' => 91.5500],
    //         ['name' => 'Baniachong', 'lat_min' => 24.5200, 'lat_max' => 24.6200, 'lng_min' => 91.2000, 'lng_max' => 91.3000],
    //         ['name' => 'Lakhai', 'lat_min' => 24.1000, 'lat_max' => 24.2000, 'lng_min' => 91.4000, 'lng_max' => 91.5000],
    //         ['name' => 'Chunarughat', 'lat_min' => 24.1500, 'lat_max' => 24.2500, 'lng_min' => 91.3500, 'lng_max' => 91.4500],
            
    //         // Sunamganj District
    //         ['name' => 'Sunamganj Sadar', 'lat_min' => 25.0600, 'lat_max' => 25.0800, 'lng_min' => 91.3900, 'lng_max' => 91.4100],
    //         ['name' => 'Chhatak', 'lat_min' => 25.0300, 'lat_max' => 25.1300, 'lng_min' => 91.6500, 'lng_max' => 91.7500],
    //         ['name' => 'Jagannathpur', 'lat_min' => 24.7000, 'lat_max' => 24.8000, 'lng_min' => 91.4500, 'lng_max' => 91.5500],
    //         ['name' => 'Derai', 'lat_min' => 24.7500, 'lat_max' => 24.8500, 'lng_min' => 91.3500, 'lng_max' => 91.4500],
    //         ['name' => 'Shantiganj', 'lat_min' => 25.2000, 'lat_max' => 25.3000, 'lng_min' => 91.2000, 'lng_max' => 91.3000],
    //         ['name' => 'Jamalganj', 'lat_min' => 25.1500, 'lat_max' => 25.2500, 'lng_min' => 91.4000, 'lng_max' => 91.5000],
    //         ['name' => 'Tahirpur', 'lat_min' => 25.2500, 'lat_max' => 25.3500, 'lng_min' => 91.4500, 'lng_max' => 91.5500],
    //         ['name' => 'Dharmapasha', 'lat_min' => 25.1000, 'lat_max' => 25.2000, 'lng_min' => 90.9500, 'lng_max' => 91.0500],
    //         ['name' => 'Bishwamvarpur', 'lat_min' => 25.0500, 'lat_max' => 25.1500, 'lng_min' => 91.0500, 'lng_max' => 91.1500],
    //         ['name' => 'Sulla', 'lat_min' => 25.1200, 'lat_max' => 25.2200, 'lng_min' => 91.2500, 'lng_max' => 91.3500],
    //         ['name' => 'Dowarabazar', 'lat_min' => 25.0000, 'lat_max' => 25.1000, 'lng_min' => 91.5000, 'lng_max' => 91.6000],
    //     ];

    //     foreach ($sylhetAreas as $area) {
    //         if ($lat >= $area['lat_min'] && $lat <= $area['lat_max'] && 
    //             $lng >= $area['lng_min'] && $lng <= $area['lng_max']) {
    //             return $area['name'];
    //         }
    //     }

    //     // Broader Sylhet Division detection
    //     if ($lat >= 24.0000 && $lat <= 25.5000 && $lng >= 91.0000 && $lng <= 92.5000) {
    //         return 'Sylhet Division';
    //     }

    //     return null;
    // }

    // /**
    //  * Check Rajshahi Division areas
    //  */
    // private function checkRajshahiAreas($lat, $lng)
    // {
    //     $rajshahiAreas = [
    //         // Rajshahi City Corporation areas
    //         ['name' => 'Rajshahi Boalia', 'lat_min' => 24.3650, 'lat_max' => 24.3850, 'lng_min' => 88.5950, 'lng_max' => 88.6150],
    //         ['name' => 'Rajshahi Matihar', 'lat_min' => 24.3500, 'lat_max' => 24.3700, 'lng_min' => 88.6000, 'lng_max' => 88.6200],
    //         ['name' => 'Rajshahi Shah Makhdum', 'lat_min' => 24.3400, 'lat_max' => 24.3600, 'lng_min' => 88.5800, 'lng_max' => 88.6000],
    //         ['name' => 'Rajshahi Rajpara', 'lat_min' => 24.3600, 'lat_max' => 24.3800, 'lng_min' => 88.5700, 'lng_max' => 88.5900],
    //         ['name' => 'Rajshahi University Area', 'lat_min' => 24.3700, 'lat_max' => 24.3900, 'lng_min' => 88.6200, 'lng_max' => 88.6400],
            
    //         // Naogaon District
    //         ['name' => 'Naogaon Sadar', 'lat_min' => 24.7900, 'lat_max' => 24.8100, 'lng_min' => 88.9300, 'lng_max' => 88.9500],
    //         ['name' => 'Patnitala', 'lat_min' => 24.8500, 'lat_max' => 24.9500, 'lng_min' => 88.8000, 'lng_max' => 88.9000],
    //         ['name' => 'Dhamoirhat', 'lat_min' => 25.1500, 'lat_max' => 25.2500, 'lng_min' => 88.8500, 'lng_max' => 88.9500],
    //         ['name' => 'Niamatpur', 'lat_min' => 25.1000, 'lat_max' => 25.2000, 'lng_min' => 88.9000, 'lng_max' => 89.0000],
    //         ['name' => 'Manda', 'lat_min' => 25.0500, 'lat_max' => 25.1500, 'lng_min' => 88.9500, 'lng_max' => 89.0500],
    //         ['name' => 'Atrai', 'lat_min' => 24.9500, 'lat_max' => 25.0500, 'lng_min' => 88.9500, 'lng_max' => 89.0500],
    //         ['name' => 'Raninagar', 'lat_min' => 24.7000, 'lat_max' => 24.8000, 'lng_min' => 88.9500, 'lng_max' => 89.0500],
    //         ['name' => 'Badalgachhi', 'lat_min' => 25.0000, 'lat_max' => 25.1000, 'lng_min' => 89.0500, 'lng_max' => 89.1500],
    //         ['name' => 'Mahadebpur', 'lat_min' => 24.9000, 'lat_max' => 25.0000, 'lng_min' => 88.8000, 'lng_max' => 88.9000],
    //         ['name' => 'Porsha', 'lat_min' => 25.0500, 'lat_max' => 25.1500, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
    //         ['name' => 'Sapahar', 'lat_min' => 24.9500, 'lat_max' => 25.0500, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
            
    //         // Pabna District
    //         ['name' => 'Pabna Sadar', 'lat_min' => 24.0000, 'lat_max' => 24.0200, 'lng_min' => 89.2300, 'lng_max' => 89.2500],
    //         ['name' => 'Ishwardi', 'lat_min' => 24.1200, 'lat_max' => 24.1400, 'lng_min' => 89.0400, 'lng_max' => 89.0600],
    //         ['name' => 'Bera', 'lat_min' => 24.0700, 'lat_max' => 24.1700, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Atgharia', 'lat_min' => 24.2000, 'lat_max' => 24.3000, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
    //         ['name' => 'Chatmohar', 'lat_min' => 24.2500, 'lat_max' => 24.3500, 'lng_min' => 89.3000, 'lng_max' => 89.4000],
    //         ['name' => 'Bhangura', 'lat_min' => 23.9000, 'lat_max' => 24.0000, 'lng_min' => 89.3500, 'lng_max' => 89.4500],
    //         ['name' => 'Faridpur (Pabna)', 'lat_min' => 23.9500, 'lat_max' => 24.0500, 'lng_min' => 89.4500, 'lng_max' => 89.5500],
    //         ['name' => 'Santhia', 'lat_min' => 24.2500, 'lat_max' => 24.2700, 'lng_min' => 89.2000, 'lng_max' => 89.2200],
    //         ['name' => 'Sujanagar', 'lat_min' => 23.8000, 'lat_max' => 23.9000, 'lng_min' => 89.1500, 'lng_max' => 89.2500],
            
    //         // Bogura District
    //         ['name' => 'Bogura Sadar', 'lat_min' => 24.8400, 'lat_max' => 24.8600, 'lng_min' => 89.3700, 'lng_max' => 89.3900],
    //         ['name' => 'Sherpur (Bogura)', 'lat_min' => 25.0200, 'lat_max' => 25.0400, 'lng_min' => 89.4200, 'lng_max' => 89.4400],
    //         ['name' => 'Dhunat', 'lat_min' => 24.7500, 'lat_max' => 24.8500, 'lng_min' => 89.5000, 'lng_max' => 89.6000],
    //         ['name' => 'Adamdighi', 'lat_min' => 24.9500, 'lat_max' => 25.0500, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
    //         ['name' => 'Nondigram', 'lat_min' => 24.9000, 'lat_max' => 25.0000, 'lng_min' => 89.4500, 'lng_max' => 89.5500],
    //         ['name' => 'Shajahanpur', 'lat_min' => 24.6000, 'lat_max' => 24.7000, 'lng_min' => 89.4000, 'lng_max' => 89.5000],
    //         ['name' => 'Dupchanchia', 'lat_min' => 24.5500, 'lat_max' => 24.6500, 'lng_min' => 89.2000, 'lng_max' => 89.3000],
    //         ['name' => 'Kahalu', 'lat_min' => 24.9000, 'lat_max' => 25.0000, 'lng_min' => 89.3000, 'lng_max' => 89.4000],
    //         ['name' => 'Nandigram', 'lat_min' => 24.7000, 'lat_max' => 24.8000, 'lng_min' => 89.4500, 'lng_max' => 89.5500],
    //         ['name' => 'Sariakandi', 'lat_min' => 24.7500, 'lat_max' => 24.8500, 'lng_min' => 89.6500, 'lng_max' => 89.7500],
    //         ['name' => 'Shibganj (Bogura)', 'lat_min' => 24.6500, 'lat_max' => 24.7500, 'lng_min' => 89.3000, 'lng_max' => 89.4000],
    //         ['name' => 'Sonatala', 'lat_min' => 24.6000, 'lat_max' => 24.7000, 'lng_min' => 89.1500, 'lng_max' => 89.2500],
            
    //         // Sirajganj District
    //         ['name' => 'Sirajganj Sadar', 'lat_min' => 24.4500, 'lat_max' => 24.4700, 'lng_min' => 89.7000, 'lng_max' => 89.7200],
    //         ['name' => 'Kamarkhand', 'lat_min' => 24.4000, 'lat_max' => 24.5000, 'lng_min' => 89.8000, 'lng_max' => 89.9000],
    //         ['name' => 'Kazipur', 'lat_min' => 24.1000, 'lat_max' => 24.2000, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Raiganj', 'lat_min' => 24.4500, 'lat_max' => 24.5500, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Shahjadpur', 'lat_min' => 24.2000, 'lat_max' => 24.3000, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Tarash', 'lat_min' => 24.3000, 'lat_max' => 24.4000, 'lng_min' => 89.4500, 'lng_max' => 89.5500],
    //         ['name' => 'Ullapara', 'lat_min' => 24.3500, 'lat_max' => 24.4500, 'lng_min' => 89.2500, 'lng_max' => 89.3500],
    //         ['name' => 'Belkuchi', 'lat_min' => 24.2500, 'lat_max' => 24.3500, 'lng_min' => 89.5500, 'lng_max' => 89.6500],
    //         ['name' => 'Chowhali', 'lat_min' => 24.5500, 'lat_max' => 24.6500, 'lng_min' => 89.7500, 'lng_max' => 89.8500],
            
    //         // Natore District
    //         ['name' => 'Natore Sadar', 'lat_min' => 24.4000, 'lat_max' => 24.4200, 'lng_min' => 88.9800, 'lng_max' => 89.0000],
    //         ['name' => 'Singra', 'lat_min' => 24.4500, 'lat_max' => 24.5500, 'lng_min' => 89.0000, 'lng_max' => 89.1000],
    //         ['name' => 'Baraigram', 'lat_min' => 24.2500, 'lat_max' => 24.3500, 'lng_min' => 89.0000, 'lng_max' => 89.1000],
    //         ['name' => 'Bagatipara', 'lat_min' => 24.1500, 'lat_max' => 24.2500, 'lng_min' => 89.0500, 'lng_max' => 89.1500],
    //         ['name' => 'Lalpur', 'lat_min' => 24.2000, 'lat_max' => 24.3000, 'lng_min' => 88.8500, 'lng_max' => 88.9500],
    //         ['name' => 'Gurudaspur', 'lat_min' => 24.4200, 'lat_max' => 24.4400, 'lng_min' => 88.8300, 'lng_max' => 88.8500],
    //         ['name' => 'Naldanga', 'lat_min' => 24.2500, 'lat_max' => 24.3500, 'lng_min' => 88.9500, 'lng_max' => 89.0500],
            
    //         // Joypurhat District
    //         ['name' => 'Joypurhat Sadar', 'lat_min' => 25.0900, 'lat_max' => 25.1100, 'lng_min' => 89.0200, 'lng_max' => 89.0400],
    //         ['name' => 'Akkelpur', 'lat_min' => 25.0000, 'lat_max' => 25.1000, 'lng_min' => 89.0000, 'lng_max' => 89.1000],
    //         ['name' => 'Kalai', 'lat_min' => 25.0500, 'lat_max' => 25.1500, 'lng_min' => 89.2000, 'lng_max' => 89.3000],
    //         ['name' => 'Khetlal', 'lat_min' => 25.1500, 'lat_max' => 25.2500, 'lng_min' => 89.0500, 'lng_max' => 89.1500],
    //         ['name' => 'Panchbibi', 'lat_min' => 25.1000, 'lat_max' => 25.2000, 'lng_min' => 89.2500, 'lng_max' => 89.3500],
            
    //         // Chapainawabganj District
    //         ['name' => 'Chapainawabganj Sadar', 'lat_min' => 24.5900, 'lat_max' => 24.6100, 'lng_min' => 88.2700, 'lng_max' => 88.2900],
    //         ['name' => 'Gomastapur', 'lat_min' => 24.8500, 'lat_max' => 24.9500, 'lng_min' => 88.2000, 'lng_max' => 88.3000],
    //         ['name' => 'Nachole', 'lat_min' => 24.9500, 'lat_max' => 25.0500, 'lng_min' => 88.2500, 'lng_max' => 88.3500],
    //         ['name' => 'Bholahat', 'lat_min' => 24.4000, 'lat_max' => 24.5000, 'lng_min' => 88.2000, 'lng_max' => 88.3000],
    //         ['name' => 'Shibganj (Chapainawabganj)', 'lat_min' => 24.6500, 'lat_max' => 24.7500, 'lng_min' => 88.1500, 'lng_max' => 88.2500],
    //     ];

    //     foreach ($rajshahiAreas as $area) {
    //         if ($lat >= $area['lat_min'] && $lat <= $area['lat_max'] && 
    //             $lng >= $area['lng_min'] && $lng <= $area['lng_max']) {
    //             return $area['name'];
    //         }
    //     }

    //     // Broader Rajshahi Division detection
    //     if ($lat >= 23.5000 && $lat <= 25.5000 && $lng >= 88.0000 && $lng <= 90.0000) {
    //         return 'Rajshahi Division';
    //     }

    //     return null;
    // }

    // /**
    //  * Check Khulna Division areas
    //  */
    // private function checkKhulnaAreas($lat, $lng)
    // {
    //     $khulnaAreas = [
    //         // Khulna City Corporation areas
    //         ['name' => 'Khulna Khalishpur', 'lat_min' => 22.8100, 'lat_max' => 22.8300, 'lng_min' => 89.5300, 'lng_max' => 89.5500],
    //         ['name' => 'Khulna Doulatpur', 'lat_min' => 22.7900, 'lat_max' => 22.8100, 'lng_min' => 89.5400, 'lng_max' => 89.5600],
    //         ['name' => 'Khulna Kotwali', 'lat_min' => 22.8000, 'lat_max' => 22.8200, 'lng_min' => 89.5500, 'lng_max' => 89.5700],
    //         ['name' => 'Khulna Sonadanga', 'lat_min' => 22.8200, 'lat_max' => 22.8400, 'lng_min' => 89.5200, 'lng_max' => 89.5400],
    //         ['name' => 'Khulna University Area', 'lat_min' => 22.8000, 'lat_max' => 22.8200, 'lng_min' => 89.5100, 'lng_max' => 89.5300],
    //         ['name' => 'Khulna Port Area', 'lat_min' => 22.7800, 'lat_max' => 22.8000, 'lng_min' => 89.5500, 'lng_max' => 89.5700],
            
    //         // Jessore District
    //         ['name' => 'Jessore Sadar', 'lat_min' => 23.1600, 'lat_max' => 23.1800, 'lng_min' => 89.2000, 'lng_max' => 89.2200],
    //         ['name' => 'Chaugachha', 'lat_min' => 23.0500, 'lat_max' => 23.1500, 'lng_min' => 89.0500, 'lng_max' => 89.1500],
    //         ['name' => 'Jhikargachha', 'lat_min' => 23.1000, 'lat_max' => 23.2000, 'lng_min' => 89.1500, 'lng_max' => 89.2500],
    //         ['name' => 'Keshabpur', 'lat_min' => 22.9000, 'lat_max' => 23.0000, 'lng_min' => 89.2000, 'lng_max' => 89.3000],
    //         ['name' => 'Manirampur', 'lat_min' => 23.0000, 'lat_max' => 23.1000, 'lng_min' => 89.2500, 'lng_max' => 89.3500],
    //         ['name' => 'Sharsha', 'lat_min' => 23.1500, 'lat_max' => 23.2500, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
    //         ['name' => 'Bagherpara', 'lat_min' => 23.0500, 'lat_max' => 23.1500, 'lng_min' => 89.4000, 'lng_max' => 89.5000],
    //         ['name' => 'Abhaynagar', 'lat_min' => 23.0000, 'lat_max' => 23.1000, 'lng_min' => 89.4500, 'lng_max' => 89.5500],
            
    //         // Satkhira District
    //         ['name' => 'Satkhira Sadar', 'lat_min' => 22.7100, 'lat_max' => 22.7300, 'lng_min' => 89.0700, 'lng_max' => 89.0900],
    //         ['name' => 'Kalaroa', 'lat_min' => 22.9000, 'lat_max' => 23.0000, 'lng_min' => 89.0000, 'lng_max' => 89.1000],
    //         ['name' => 'Tala', 'lat_min' => 22.7500, 'lat_max' => 22.8500, 'lng_min' => 89.2000, 'lng_max' => 89.3000],
    //         ['name' => 'Debhata', 'lat_min' => 22.5500, 'lat_max' => 22.6500, 'lng_min' => 89.1500, 'lng_max' => 89.2500],
    //         ['name' => 'Kaliganj (Satkhira)', 'lat_min' => 22.4500, 'lat_max' => 22.5500, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
    //         ['name' => 'Shyamnagar', 'lat_min' => 22.3000, 'lat_max' => 22.4000, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
    //         ['name' => 'Assasuni', 'lat_min' => 22.5000, 'lat_max' => 22.6000, 'lng_min' => 89.0000, 'lng_max' => 89.1000],
            
    //         // Narail District
    //         ['name' => 'Narail Sadar', 'lat_min' => 23.1500, 'lat_max' => 23.1700, 'lng_min' => 89.4900, 'lng_max' => 89.5100],
    //         ['name' => 'Lohagara (Narail)', 'lat_min' => 22.9500, 'lat_max' => 23.0500, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Kalia', 'lat_min' => 23.0500, 'lat_max' => 23.1500, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
            
    //         // Bagerhat District
    //         ['name' => 'Bagerhat Sadar', 'lat_min' => 22.6500, 'lat_max' => 22.6700, 'lng_min' => 89.7800, 'lng_max' => 89.8000],
    //         ['name' => 'Mongla', 'lat_min' => 22.4800, 'lat_max' => 22.5000, 'lng_min' => 89.5900, 'lng_max' => 89.6100],
    //         ['name' => 'Sharankhola', 'lat_min' => 22.3000, 'lat_max' => 22.4000, 'lng_min' => 89.8000, 'lng_max' => 89.9000],
    //         ['name' => 'Morrelganj', 'lat_min' => 22.4500, 'lat_max' => 22.5500, 'lng_min' => 89.8500, 'lng_max' => 89.9500],
    //         ['name' => 'Kachua (Bagerhat)', 'lat_min' => 22.6000, 'lat_max' => 22.7000, 'lng_min' => 89.5000, 'lng_max' => 89.6000],
    //         ['name' => 'Mollahat', 'lat_min' => 22.8500, 'lat_max' => 22.9500, 'lng_min' => 89.7500, 'lng_max' => 89.8500],
    //         ['name' => 'Chitalmari', 'lat_min' => 22.7000, 'lat_max' => 22.8000, 'lng_min' => 89.7000, 'lng_max' => 89.8000],
    //         ['name' => 'Fakirhat', 'lat_min' => 22.7000, 'lat_max' => 22.8000, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Rampal', 'lat_min' => 22.5500, 'lat_max' => 22.6500, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
            
    //         // Chuadanga District
    //         ['name' => 'Chuadanga Sadar', 'lat_min' => 23.6400, 'lat_max' => 23.6600, 'lng_min' => 88.8400, 'lng_max' => 88.8600],
    //         ['name' => 'Alamdanga', 'lat_min' => 23.9000, 'lat_max' => 24.0000, 'lng_min' => 88.9000, 'lng_max' => 89.0000],
    //         ['name' => 'Damurhuda', 'lat_min' => 23.5500, 'lat_max' => 23.6500, 'lng_min' => 88.9000, 'lng_max' => 89.0000],
    //         ['name' => 'Jibannagar', 'lat_min' => 23.8000, 'lat_max' => 23.9000, 'lng_min' => 88.8000, 'lng_max' => 88.9000],
            
    //         // Kushtia District
    //         ['name' => 'Kushtia Sadar', 'lat_min' => 23.9000, 'lat_max' => 23.9200, 'lng_min' => 89.1100, 'lng_max' => 89.1300],
    //         ['name' => 'Kumarkhali', 'lat_min' => 23.8000, 'lat_max' => 23.9000, 'lng_min' => 89.2500, 'lng_max' => 89.3500],
    //         ['name' => 'Khoksa', 'lat_min' => 23.5500, 'lat_max' => 23.6500, 'lng_min' => 89.1500, 'lng_max' => 89.2500],
    //         ['name' => 'Daulatpur (Kushtia)', 'lat_min' => 23.8000, 'lat_max' => 23.9000, 'lng_min' => 89.1500, 'lng_max' => 89.2500],
    //         ['name' => 'Mirpur (Kushtia)', 'lat_min' => 23.8500, 'lat_max' => 23.9500, 'lng_min' => 89.0500, 'lng_max' => 89.1500],
    //         ['name' => 'Bheramara', 'lat_min' => 24.0000, 'lat_max' => 24.1000, 'lng_min' => 89.0000, 'lng_max' => 89.1000],
            
    //         // Meherpur District
    //         ['name' => 'Meherpur Sadar', 'lat_min' => 23.7600, 'lat_max' => 23.7800, 'lng_min' => 88.6300, 'lng_max' => 88.6500],
    //         ['name' => 'Mujibnagar', 'lat_min' => 23.6500, 'lat_max' => 23.7500, 'lng_min' => 88.6000, 'lng_max' => 88.7000],
    //         ['name' => 'Gangni', 'lat_min' => 23.6500, 'lat_max' => 23.7500, 'lng_min' => 88.5000, 'lng_max' => 88.6000],
            
    //         // Magura District
    //         ['name' => 'Magura Sadar', 'lat_min' => 23.4800, 'lat_max' => 23.5000, 'lng_min' => 89.4600, 'lng_max' => 89.4800],
    //         ['name' => 'Mohammadpur (Magura)', 'lat_min' => 23.4000, 'lat_max' => 23.5000, 'lng_min' => 89.3500, 'lng_max' => 89.4500],
    //         ['name' => 'Shalikha', 'lat_min' => 23.3000, 'lat_max' => 23.4000, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Sreepur (Magura)', 'lat_min' => 23.1500, 'lat_max' => 23.2500, 'lng_min' => 89.7000, 'lng_max' => 89.8000],
            
    //         // Jhenaidah District
    //         ['name' => 'Jhenaidah Sadar', 'lat_min' => 23.5400, 'lat_max' => 23.5600, 'lng_min' => 89.1700, 'lng_max' => 89.1900],
    //         ['name' => 'Shailkupa', 'lat_min' => 23.4500, 'lat_max' => 23.5500, 'lng_min' => 89.2500, 'lng_max' => 89.3500],
    //         ['name' => 'Harinakundu', 'lat_min' => 23.6000, 'lat_max' => 23.7000, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
    //         ['name' => 'Kaliganj (Jhenaidah)', 'lat_min' => 23.4000, 'lat_max' => 23.5000, 'lng_min' => 89.1000, 'lng_max' => 89.2000],
    //         ['name' => 'Kotchandpur', 'lat_min' => 23.5000, 'lat_max' => 23.6000, 'lng_min' => 89.0500, 'lng_max' => 89.1500],
    //         ['name' => 'Maheshpur', 'lat_min' => 23.4000, 'lat_max' => 23.5000, 'lng_min' => 89.0000, 'lng_max' => 89.1000],
    //     ];

    //     foreach ($khulnaAreas as $area) {
    //         if ($lat >= $area['lat_min'] && $lat <= $area['lat_max'] && 
    //             $lng >= $area['lng_min'] && $lng <= $area['lng_max']) {
    //             return $area['name'];
    //         }
    //     }

    //     // Broader Khulna Division detection
    //     if ($lat >= 22.0000 && $lat <= 24.5000 && $lng >= 88.5000 && $lng <= 90.0000) {
    //         return 'Khulna Division';
    //     }

    //     return null;
    // }

    // /**
    //  * Check Barisal Division areas
    //  */
    // private function checkBarisalAreas($lat, $lng)
    // {
    //     $barisalAreas = [
    //         // Barisal City Corporation areas
    //         ['name' => 'Barisal Kotwali', 'lat_min' => 22.7000, 'lat_max' => 22.7200, 'lng_min' => 90.3600, 'lng_max' => 90.3800],
    //         ['name' => 'Barisal Airport Area', 'lat_min' => 22.8000, 'lat_max' => 22.8200, 'lng_min' => 90.3000, 'lng_max' => 90.3200],
    //         ['name' => 'Barisal BM College Area', 'lat_min' => 22.7100, 'lat_max' => 22.7300, 'lng_min' => 90.3500, 'lng_max' => 90.3700],
    //         ['name' => 'Barisal Port Area', 'lat_min' => 22.6900, 'lat_max' => 22.7100, 'lng_min' => 90.3700, 'lng_max' => 90.3900],
            
    //         // Patuakhali District
    //         ['name' => 'Patuakhali Sadar', 'lat_min' => 22.3500, 'lat_max' => 22.3700, 'lng_min' => 90.3200, 'lng_max' => 90.3400],
    //         ['name' => 'Kuakata', 'lat_min' => 21.8000, 'lat_max' => 21.8200, 'lng_min' => 90.1200, 'lng_max' => 90.1400],
    //         ['name' => 'Galachipa', 'lat_min' => 22.1500, 'lat_max' => 22.2500, 'lng_min' => 90.2000, 'lng_max' => 90.3000],
    //         ['name' => 'Kalapara', 'lat_min' => 21.9000, 'lat_max' => 22.0000, 'lng_min' => 90.2000, 'lng_max' => 90.3000],
    //         ['name' => 'Dumki', 'lat_min' => 22.5000, 'lat_max' => 22.6000, 'lng_min' => 90.1000, 'lng_max' => 90.2000],
    //         ['name' => 'Dashmina', 'lat_min' => 22.3000, 'lat_max' => 22.4000, 'lng_min' => 90.4500, 'lng_max' => 90.5500],
    //         ['name' => 'Bauphal', 'lat_min' => 22.4000, 'lat_max' => 22.5000, 'lng_min' => 90.3500, 'lng_max' => 90.4500],
    //         ['name' => 'Mirzaganj', 'lat_min' => 22.1000, 'lat_max' => 22.2000, 'lng_min' => 90.1500, 'lng_max' => 90.2500],
            
    //         // Pirojpur District
    //         ['name' => 'Pirojpur Sadar', 'lat_min' => 22.5800, 'lat_max' => 22.6000, 'lng_min' => 89.9700, 'lng_max' => 89.9900],
    //         ['name' => 'Nazirpur', 'lat_min' => 22.9000, 'lat_max' => 23.0000, 'lng_min' => 89.9000, 'lng_max' => 90.0000],
    //         ['name' => 'Kawkhali', 'lat_min' => 22.5000, 'lat_max' => 22.6000, 'lng_min' => 90.0000, 'lng_max' => 90.1000],
    //         ['name' => 'Bhandaria', 'lat_min' => 22.4000, 'lat_max' => 22.5000, 'lng_min' => 90.0500, 'lng_max' => 90.1500],
    //         ['name' => 'Mathbaria', 'lat_min' => 22.2500, 'lat_max' => 22.3500, 'lng_min' => 89.9500, 'lng_max' => 90.0500],
    //         ['name' => 'Nesarabad', 'lat_min' => 22.6500, 'lat_max' => 22.7500, 'lng_min' => 89.9500, 'lng_max' => 90.0500],
    //         ['name' => 'Indurkani', 'lat_min' => 22.3000, 'lat_max' => 22.4000, 'lng_min' => 90.1000, 'lng_max' => 90.2000],
            
    //         // Jhalokati District
    //         ['name' => 'Jhalokati Sadar', 'lat_min' => 22.6400, 'lat_max' => 22.6600, 'lng_min' => 90.1900, 'lng_max' => 90.2100],
    //         ['name' => 'Kathalia', 'lat_min' => 22.6000, 'lat_max' => 22.7000, 'lng_min' => 90.1000, 'lng_max' => 90.2000],
    //         ['name' => 'Nalchity', 'lat_min' => 22.6500, 'lat_max' => 22.7500, 'lng_min' => 90.2500, 'lng_max' => 90.3500],
    //         ['name' => 'Rajapur', 'lat_min' => 22.5500, 'lat_max' => 22.6500, 'lng_min' => 90.1500, 'lng_max' => 90.2500],
            
    //         // Barguna District
    //         ['name' => 'Barguna Sadar', 'lat_min' => 22.1500, 'lat_max' => 22.1700, 'lng_min' => 90.1200, 'lng_max' => 90.1400],
    //         ['name' => 'Amtali', 'lat_min' => 22.1000, 'lat_max' => 22.2000, 'lng_min' => 90.2000, 'lng_max' => 90.3000],
    //         ['name' => 'Betagi', 'lat_min' => 22.3000, 'lat_max' => 22.4000, 'lng_min' => 90.0500, 'lng_max' => 90.1500],
    //         ['name' => 'Bamna', 'lat_min' => 22.7500, 'lat_max' => 22.8500, 'lng_min' => 90.0000, 'lng_max' => 90.1000],
    //         ['name' => 'Pathorghata', 'lat_min' => 22.0500, 'lat_max' => 22.1500, 'lng_min' => 90.0000, 'lng_max' => 90.1000],
    //         ['name' => 'Taltali', 'lat_min' => 21.9000, 'lat_max' => 22.0000, 'lng_min' => 90.1500, 'lng_max' => 90.2500],
            
    //         // Bhola District
    //         ['name' => 'Bhola Sadar', 'lat_min' => 22.6900, 'lat_max' => 22.7100, 'lng_min' => 90.6500, 'lng_max' => 90.6700],
    //         ['name' => 'Borhanuddin', 'lat_min' => 22.6000, 'lat_max' => 22.7000, 'lng_min' => 90.7000, 'lng_max' => 90.8000],
    //         ['name' => 'Char Fasson', 'lat_min' => 22.2500, 'lat_max' => 22.3500, 'lng_min' => 90.7000, 'lng_max' => 90.8000],
    //         ['name' => 'Daulatkhan', 'lat_min' => 22.6000, 'lat_max' => 22.7000, 'lng_min' => 90.7500, 'lng_max' => 90.8500],
    //         ['name' => 'Lalmohan', 'lat_min' => 22.3500, 'lat_max' => 22.4500, 'lng_min' => 90.7500, 'lng_max' => 90.8500],
    //         ['name' => 'Manpura', 'lat_min' => 22.0500, 'lat_max' => 22.1500, 'lng_min' => 90.6500, 'lng_max' => 90.7500],
    //         ['name' => 'Tazumuddin', 'lat_min' => 22.5000, 'lat_max' => 22.6000, 'lng_min' => 90.8500, 'lng_max' => 90.9500],
    //     ];

    //     foreach ($barisalAreas as $area) {
    //         if ($lat >= $area['lat_min'] && $lat <= $area['lat_max'] && 
    //             $lng >= $area['lng_min'] && $lng <= $area['lng_max']) {
    //             return $area['name'];
    //         }
    //     }

    //     // Broader Barisal Division detection
    //     if ($lat >= 21.5000 && $lat <= 23.5000 && $lng >= 89.8000 && $lng <= 91.0000) {
    //         return 'Barisal Division';
    //     }

    //     return null;
    // }

    // /**
    //  * Check Rangpur Division areas
    //  */
    // private function checkRangpurAreas($lat, $lng)
    // {
    //     $rangpurAreas = [
    //         // Rangpur City Corporation areas
    //         ['name' => 'Rangpur Kotwali', 'lat_min' => 25.7400, 'lat_max' => 25.7600, 'lng_min' => 89.2400, 'lng_max' => 89.2600],
    //         ['name' => 'Rangpur Cantonment', 'lat_min' => 25.7600, 'lat_max' => 25.7800, 'lng_min' => 89.2200, 'lng_max' => 89.2400],
    //         ['name' => 'Rangpur Medical Area', 'lat_min' => 25.7200, 'lat_max' => 25.7400, 'lng_min' => 89.2500, 'lng_max' => 89.2700],
    //         ['name' => 'Rangpur Station Road', 'lat_min' => 25.7300, 'lat_max' => 25.7500, 'lng_min' => 89.2300, 'lng_max' => 89.2500],
            
    //         // Dinajpur District
    //         ['name' => 'Dinajpur Sadar', 'lat_min' => 25.6200, 'lat_max' => 25.6400, 'lng_min' => 88.6300, 'lng_max' => 88.6500],
    //         ['name' => 'Birampur', 'lat_min' => 25.8500, 'lat_max' => 25.9500, 'lng_min' => 88.6000, 'lng_max' => 88.7000],
    //         ['name' => 'Birganj', 'lat_min' => 25.6500, 'lat_max' => 25.7500, 'lng_min' => 88.5000, 'lng_max' => 88.6000],
    //         ['name' => 'Biral', 'lat_min' => 25.8000, 'lat_max' => 25.9000, 'lng_min' => 88.6500, 'lng_max' => 88.7500],
    //         ['name' => 'Bochaganj', 'lat_min' => 25.9000, 'lat_max' => 26.0000, 'lng_min' => 88.4500, 'lng_max' => 88.5500],
    //         ['name' => 'Chirirbandar', 'lat_min' => 25.8000, 'lat_max' => 25.9000, 'lng_min' => 88.4000, 'lng_max' => 88.5000],
    //         ['name' => 'Phulbari (Dinajpur)', 'lat_min' => 25.9500, 'lat_max' => 26.0500, 'lng_min' => 88.5500, 'lng_max' => 88.6500],
    //         ['name' => 'Ghoraghat', 'lat_min' => 25.7000, 'lat_max' => 25.8000, 'lng_min' => 88.4500, 'lng_max' => 88.5500],
    //         ['name' => 'Hakimpur', 'lat_min' => 25.8500, 'lat_max' => 25.9500, 'lng_min' => 88.8000, 'lng_max' => 88.9000],
    //         ['name' => 'Kaharole', 'lat_min' => 25.7500, 'lat_max' => 25.8500, 'lng_min' => 88.6500, 'lng_max' => 88.7500],
    //         ['name' => 'Khansama', 'lat_min' => 25.5000, 'lat_max' => 25.6000, 'lng_min' => 88.7500, 'lng_max' => 88.8500],
    //         ['name' => 'Nawabganj (Dinajpur)', 'lat_min' => 25.3000, 'lat_max' => 25.4000, 'lng_min' => 88.2500, 'lng_max' => 88.3500],
    //         ['name' => 'Parbatipur', 'lat_min' => 25.6500, 'lat_max' => 25.6700, 'lng_min' => 88.9300, 'lng_max' => 88.9500],
            
    //         // Gaibandha District
    //         ['name' => 'Gaibandha Sadar', 'lat_min' => 25.3200, 'lat_max' => 25.3400, 'lng_min' => 89.5300, 'lng_max' => 89.5500],
    //         ['name' => 'Gobindaganj', 'lat_min' => 25.1000, 'lat_max' => 25.2000, 'lng_min' => 89.2000, 'lng_max' => 89.3000],
    //         ['name' => 'Palashbari', 'lat_min' => 25.8500, 'lat_max' => 25.9500, 'lng_min' => 89.2000, 'lng_max' => 89.3000],
    //         ['name' => 'Saghata', 'lat_min' => 25.1500, 'lat_max' => 25.2500, 'lng_min' => 89.3000, 'lng_max' => 89.4000],
    //         ['name' => 'Phulchhari', 'lat_min' => 25.6000, 'lat_max' => 25.7000, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Sadullapur', 'lat_min' => 25.3500, 'lat_max' => 25.4500, 'lng_min' => 89.7000, 'lng_max' => 89.8000],
    //         ['name' => 'Sundarganj', 'lat_min' => 25.4000, 'lat_max' => 25.5000, 'lng_min' => 89.5000, 'lng_max' => 89.6000],
            
    //         // Kurigram District
    //         ['name' => 'Kurigram Sadar', 'lat_min' => 25.8000, 'lat_max' => 25.8200, 'lng_min' => 89.6300, 'lng_max' => 89.6500],
    //         ['name' => 'Nageshwari', 'lat_min' => 25.9500, 'lat_max' => 26.0500, 'lng_min' => 89.7000, 'lng_max' => 89.8000],
    //         ['name' => 'Bhurungamari', 'lat_min' => 25.9000, 'lat_max' => 26.0000, 'lng_min' => 89.4500, 'lng_max' => 89.5500],
    //         ['name' => 'Phulbari (Kurigram)', 'lat_min' => 25.7000, 'lat_max' => 25.8000, 'lng_min' => 89.4500, 'lng_max' => 89.5500],
    //         ['name' => 'Rajarhat', 'lat_min' => 25.5500, 'lat_max' => 25.6500, 'lng_min' => 89.5500, 'lng_max' => 89.6500],
    //         ['name' => 'Ulipur', 'lat_min' => 25.6500, 'lat_max' => 25.7500, 'lng_min' => 89.6500, 'lng_max' => 89.7500],
    //         ['name' => 'Chilmari', 'lat_min' => 25.5000, 'lat_max' => 25.6000, 'lng_min' => 89.6500, 'lng_max' => 89.7500],
    //         ['name' => 'Rowmari', 'lat_min' => 25.8500, 'lat_max' => 25.9500, 'lng_min' => 89.7500, 'lng_max' => 89.8500],
    //         ['name' => 'Char Rajibpur', 'lat_min' => 25.7500, 'lat_max' => 25.8500, 'lng_min' => 89.7000, 'lng_max' => 89.8000],
            
    //         // Lalmonirhat District
    //         ['name' => 'Lalmonirhat Sadar', 'lat_min' => 25.9100, 'lat_max' => 25.9300, 'lng_min' => 89.4400, 'lng_max' => 89.4600],
    //         ['name' => 'Aditmari', 'lat_min' => 25.6500, 'lat_max' => 25.7500, 'lng_min' => 89.0000, 'lng_max' => 89.1000],
    //         ['name' => 'Kaliganj (Lalmonirhat)', 'lat_min' => 25.6000, 'lat_max' => 25.7000, 'lng_min' => 89.1500, 'lng_max' => 89.2500],
    //         ['name' => 'Hatibandha', 'lat_min' => 25.8500, 'lat_max' => 25.9500, 'lng_min' => 89.3000, 'lng_max' => 89.4000],
    //         ['name' => 'Patgram', 'lat_min' => 25.6500, 'lat_max' => 25.7500, 'lng_min' => 89.3500, 'lng_max' => 89.4500],
            
    //         // Nilphamari District
    //         ['name' => 'Nilphamari Sadar', 'lat_min' => 25.9300, 'lat_max' => 25.9500, 'lng_min' => 88.8500, 'lng_max' => 88.8700],
    //         ['name' => 'Saidpur', 'lat_min' => 25.7700, 'lat_max' => 25.7900, 'lng_min' => 88.8900, 'lng_max' => 88.9100],
    //         ['name' => 'Domar', 'lat_min' => 25.8500, 'lat_max' => 25.9500, 'lng_min' => 88.9000, 'lng_max' => 89.0000],
    //         ['name' => 'Dimla', 'lat_min' => 26.0000, 'lat_max' => 26.1000, 'lng_min' => 88.7000, 'lng_max' => 88.8000],
    //         ['name' => 'Jaldhaka', 'lat_min' => 25.8000, 'lat_max' => 25.9000, 'lng_min' => 88.7500, 'lng_max' => 88.8500],
    //         ['name' => 'Kishoreganj (Nilphamari)', 'lat_min' => 25.8500, 'lat_max' => 25.9500, 'lng_min' => 88.9500, 'lng_max' => 89.0500],
            
    //         // Thakurgaon District
    //         ['name' => 'Thakurgaon Sadar', 'lat_min' => 26.0300, 'lat_max' => 26.0500, 'lng_min' => 88.4600, 'lng_max' => 88.4800],
    //         ['name' => 'Pirganj (Thakurgaon)', 'lat_min' => 25.8500, 'lat_max' => 25.9500, 'lng_min' => 88.3500, 'lng_max' => 88.4500],
    //         ['name' => 'Ranisankail', 'lat_min' => 25.9500, 'lat_max' => 26.0500, 'lng_min' => 88.5000, 'lng_max' => 88.6000],
    //         ['name' => 'Haripur', 'lat_min' => 26.0500, 'lat_max' => 26.1500, 'lng_min' => 88.0500, 'lng_max' => 88.1500],
    //         ['name' => 'Baliadangi', 'lat_min' => 26.1000, 'lat_max' => 26.2000, 'lng_min' => 88.3000, 'lng_max' => 88.4000],
            
    //         // Panchagarh District
    //         ['name' => 'Panchagarh Sadar', 'lat_min' => 26.3300, 'lat_max' => 26.3500, 'lng_min' => 88.5500, 'lng_max' => 88.5700],
    //         ['name' => 'Debiganj', 'lat_min' => 26.0000, 'lat_max' => 26.1000, 'lng_min' => 88.2000, 'lng_max' => 88.3000],
    //         ['name' => 'Boda', 'lat_min' => 26.1500, 'lat_max' => 26.2500, 'lng_min' => 88.4500, 'lng_max' => 88.5500],
    //         ['name' => 'Atwari', 'lat_min' => 26.3000, 'lat_max' => 26.4000, 'lng_min' => 88.4000, 'lng_max' => 88.5000],
    //         ['name' => 'Tetulia', 'lat_min' => 26.4000, 'lat_max' => 26.5000, 'lng_min' => 88.4500, 'lng_max' => 88.5500],
    //     ];

    //     foreach ($rangpurAreas as $area) {
    //         if ($lat >= $area['lat_min'] && $lat <= $area['lat_max'] && 
    //             $lng >= $area['lng_min'] && $lng <= $area['lng_max']) {
    //             return $area['name'];
    //         }
    //     }

    //     // Broader Rangpur Division detection
    //     if ($lat >= 25.0000 && $lat <= 26.6000 && $lng >= 88.0000 && $lng <= 90.0000) {
    //         return 'Rangpur Division';
    //     }

    //     return null;
    // }

    // /**
    //  * Check Mymensingh Division areas
    //  */
    // private function checkMymensinghAreas($lat, $lng)
    // {
    //     $mymensinghAreas = [
    //         // Mymensingh City Corporation areas
    //         ['name' => 'Mymensingh Kotwali', 'lat_min' => 24.7400, 'lat_max' => 24.7600, 'lng_min' => 90.4000, 'lng_max' => 90.4200],
    //         ['name' => 'Mymensingh Medical Area', 'lat_min' => 24.7500, 'lat_max' => 24.7700, 'lng_min' => 90.3800, 'lng_max' => 90.4000],
    //         ['name' => 'Mymensingh BAU Area', 'lat_min' => 24.7200, 'lat_max' => 24.7400, 'lng_min' => 90.4200, 'lng_max' => 90.4400],
    //         ['name' => 'Mymensingh Cantonment', 'lat_min' => 24.7300, 'lat_max' => 24.7500, 'lng_min' => 90.3600, 'lng_max' => 90.3800],
            
    //         // Jamalpur District
    //         ['name' => 'Jamalpur Sadar', 'lat_min' => 24.9200, 'lat_max' => 24.9400, 'lng_min' => 89.9400, 'lng_max' => 89.9600],
    //         ['name' => 'Melandaha', 'lat_min' => 24.7500, 'lat_max' => 24.8500, 'lng_min' => 90.0000, 'lng_max' => 90.1000],
    //         ['name' => 'Islampur (Jamalpur)', 'lat_min' => 24.8000, 'lat_max' => 24.9000, 'lng_min' => 89.8500, 'lng_max' => 89.9500],
    //         ['name' => 'Dewanganj', 'lat_min' => 25.0500, 'lat_max' => 25.1500, 'lng_min' => 89.8000, 'lng_max' => 89.9000],
    //         ['name' => 'Bakshiganj', 'lat_min' => 25.0000, 'lat_max' => 25.1000, 'lng_min' => 89.6000, 'lng_max' => 89.7000],
    //         ['name' => 'Madarganj', 'lat_min' => 24.8500, 'lat_max' => 24.9500, 'lng_min' => 89.7500, 'lng_max' => 89.8500],
    //         ['name' => 'Sarishabari', 'lat_min' => 24.7000, 'lat_max' => 24.8000, 'lng_min' => 89.8000, 'lng_max' => 89.9000],
            
    //         // Sherpur District
    //         ['name' => 'Sherpur Sadar', 'lat_min' => 25.0200, 'lat_max' => 25.0400, 'lng_min' => 90.0100, 'lng_max' => 90.0300],
    //         ['name' => 'Nalitabari', 'lat_min' => 25.1000, 'lat_max' => 25.2000, 'lng_min' => 90.1000, 'lng_max' => 90.2000],
    //         ['name' => 'Sreebordi', 'lat_min' => 25.1500, 'lat_max' => 25.2500, 'lng_min' => 90.0000, 'lng_max' => 90.1000],
    //         ['name' => 'Nokla', 'lat_min' => 25.0000, 'lat_max' => 25.1000, 'lng_min' => 90.1500, 'lng_max' => 90.2500],
    //         ['name' => 'Jhenaigati', 'lat_min' => 24.9000, 'lat_max' => 25.0000, 'lng_min' => 90.0500, 'lng_max' => 90.1500],
            
    //         // Netrokona District
    //         ['name' => 'Netrokona Sadar', 'lat_min' => 24.8700, 'lat_max' => 24.8900, 'lng_min' => 90.7300, 'lng_max' => 90.7500],
    //         ['name' => 'Atpara', 'lat_min' => 24.8000, 'lat_max' => 24.9000, 'lng_min' => 90.6000, 'lng_max' => 90.7000],
    //         ['name' => 'Barhatta', 'lat_min' => 24.9500, 'lat_max' => 25.0500, 'lng_min' => 90.7500, 'lng_max' => 90.8500],
    //         ['name' => 'Durgapur (Netrokona)', 'lat_min' => 24.7500, 'lat_max' => 24.8500, 'lng_min' => 90.6500, 'lng_max' => 90.7500],
    //         ['name' => 'Kalmakanda', 'lat_min' => 24.9000, 'lat_max' => 25.0000, 'lng_min' => 90.8000, 'lng_max' => 90.9000],
    //         ['name' => 'Kendua', 'lat_min' => 24.7000, 'lat_max' => 24.8000, 'lng_min' => 90.8000, 'lng_max' => 90.9000],
    //         ['name' => 'Madan', 'lat_min' => 24.9500, 'lat_max' => 25.0500, 'lng_min' => 90.6500, 'lng_max' => 90.7500],
    //         ['name' => 'Mohanganj', 'lat_min' => 24.7000, 'lat_max' => 24.8000, 'lng_min' => 90.5500, 'lng_max' => 90.6500],
    //         ['name' => 'Purbadhala', 'lat_min' => 25.0000, 'lat_max' => 25.1000, 'lng_min' => 90.8500, 'lng_max' => 90.9500],
    //         ['name' => 'Khaliajuri', 'lat_min' => 24.6500, 'lat_max' => 24.7500, 'lng_min' => 90.7500, 'lng_max' => 90.8500],
    //     ];

    //     foreach ($mymensinghAreas as $area) {
    //         if ($lat >= $area['lat_min'] && $lat <= $area['lat_max'] && 
    //             $lng >= $area['lng_min'] && $lng <= $area['lng_max']) {
    //             return $area['name'];
    //         }
    //     }

    //     // Broader Mymensingh Division detection
    //     if ($lat >= 24.5000 && $lat <= 25.5000 && $lng >= 89.5000 && $lng <= 91.5000) {
    //         return 'Mymensingh Division';
    //     }

    //     return null;
    // }

    // /**
    //  * Check broader Bangladesh divisions if specific area not found
    //  */
    // private function checkBangladeshDivisions($lat, $lng)
    // {
    //     // Broader divisional detection if specific areas not found
    //     if ($lat >= 23.0000 && $lat <= 24.5000 && $lng >= 90.0000 && $lng <= 90.8000) {
    //         return 'Dhaka Division';
    //     }
        
    //     if ($lat >= 20.5000 && $lat <= 24.5000 && $lng >= 90.5000 && $lng <= 92.8000) {
    //         return 'Chittagong Division';
    //     }

    //     if ($lat >= 24.0000 && $lat <= 25.5000 && $lng >= 91.0000 && $lng <= 92.5000) {
    //         return 'Sylhet Division';
    //     }

    //     if ($lat >= 23.5000 && $lat <= 25.5000 && $lng >= 88.0000 && $lng <= 90.0000) {
    //         return 'Rajshahi Division';
    //     }

    //     if ($lat >= 22.0000 && $lat <= 24.5000 && $lng >= 88.5000 && $lng <= 90.0000) {
    //         return 'Khulna Division';
    //     }

    //     if ($lat >= 21.5000 && $lat <= 23.5000 && $lng >= 89.8000 && $lng <= 91.0000) {
    //         return 'Barisal Division';
    //     }

    //     if ($lat >= 25.0000 && $lat <= 26.6000 && $lng >= 88.0000 && $lng <= 90.0000) {
    //         return 'Rangpur Division';
    //     }

    //     if ($lat >= 24.5000 && $lat <= 25.5000 && $lng >= 89.5000 && $lng <= 91.5000) {
    //         return 'Mymensingh Division';
    //     }

    //     return null;
    // }



}
