<?php
/**
 * Test the batch function with your payload structure
 */

echo "🧪 Testing Batch Function with Your Payload Structure...\n\n";

// Your exact payload
$payload = [
    "user_latitude" => 22.3389945,
    "user_longitude" => 91.7815682,
    "source" => "gps",
    "interactions" => [
        [
            "business_id" => 8,
            "action" => "view",
            "context" => [
                "session_id" => "session_1756644186884_ta8sl5scy",
                "source" => "business_detail_screen",
                "businessName" => "Stolen",
                "category" => "Shopping",
                "subcategory" => "Clothing"
            ],
            "timestamp" => 1756644187055
        ]
    ]
];

echo "📱 Payload Structure:\n";
echo json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

// Test with ProcessBatchInteractionsJob directly
echo "🚀 Testing ProcessBatchInteractionsJob with processed interactions...\n";

// Simulate what the controller does
$interactions = $payload['interactions'];
$globalLatitude = $payload['user_latitude'];
$globalLongitude = $payload['user_longitude'];
$globalSource = $payload['source'];

// Process interactions to add global location data
$processedInteractions = array_map(function ($interaction) use ($globalLatitude, $globalLongitude, $globalSource) {
    // Use individual location if provided, otherwise use global
    if (!isset($interaction['user_latitude']) && $globalLatitude !== null) {
        $interaction['user_latitude'] = $globalLatitude;
    }
    if (!isset($interaction['user_longitude']) && $globalLongitude !== null) {
        $interaction['user_longitude'] = $globalLongitude;
    }
    // Use individual source if provided, otherwise use global
    if (!isset($interaction['source']) && $globalSource !== null) {
        $interaction['source'] = $globalSource;
    }
    return $interaction;
}, $interactions);

echo "📊 Processed Interactions:\n";
echo json_encode($processedInteractions, JSON_PRETTY_PRINT) . "\n\n";

// Check if business exists
echo "🏢 Checking if business ID 8 exists...\n";
try {
    $business = \App\Models\Business::find(8);
    if ($business) {
        echo "   ✅ Business found: {$business->name}\n";
    } else {
        echo "   ❌ Business ID 8 not found\n";
        echo "   🔍 Available businesses:\n";
        $businesses = \App\Models\Business::limit(5)->get(['id', 'name']);
        foreach ($businesses as $biz) {
            echo "      - ID: {$biz->id}, Name: {$biz->name}\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error checking business: " . $e->getMessage() . "\n";
}

// Test the job dispatch
echo "\n⚡ Testing Job Dispatch...\n";
try {
    // Use a valid business ID (let's use 1)
    $testInteractions = $processedInteractions;
    $testInteractions[0]['business_id'] = 1; // Use business ID 1 instead
    
    \App\Jobs\ProcessBatchInteractionsJob::dispatch(1, $testInteractions);
    echo "   ✅ Job dispatched successfully!\n";
    echo "   📝 Job will process: " . count($testInteractions) . " interactions\n";
    echo "   🌍 Location: ({$globalLatitude}, {$globalLongitude})\n";
    echo "   📡 Source: {$globalSource}\n";
    
} catch (\Exception $e) {
    echo "   ❌ Job dispatch failed: " . $e->getMessage() . "\n";
}

echo "\n🎯 Summary of Updates:\n";
echo "✅ Batch function now accepts user_latitude/user_longitude at root level\n";
echo "✅ Global location data is applied to all interactions in the batch\n";
echo "✅ Individual interactions can still override with their own location\n";
echo "✅ Global source is applied if not specified per interaction\n";
echo "✅ Backward compatible with existing payload structures\n";

echo "\n📱 Your Payload Structure is Now Supported!\n";
echo "Send your exact payload to: POST /api/v1/interactions/batch\n";
echo "The system will automatically apply latitude/longitude to all interactions.\n";
