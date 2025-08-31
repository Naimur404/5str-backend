<?php
/**
 * Direct test of UserInteraction tracking
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\UserInteraction;
use Illuminate\Support\Facades\DB;

echo "🧪 Testing UserInteraction::track method directly...\n\n";

// Check current count
$beforeCount = DB::table('user_interactions')->count();
echo "Records before: {$beforeCount}\n";

try {
    // Test the track method directly
    $interaction = UserInteraction::track(
        1, // user_id
        1, // business_id
        'view', // action
        'direct_test', // source
        ['test' => true] // context
    );
    
    echo "✅ UserInteraction::track succeeded!\n";
    echo "Interaction ID: {$interaction->id}\n";
    
    // Check new count
    $afterCount = DB::table('user_interactions')->count();
    echo "Records after: {$afterCount}\n";
    echo "New records created: " . ($afterCount - $beforeCount) . "\n";
    
} catch (\Exception $e) {
    echo "❌ UserInteraction::track failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n🔍 Let's also check the table structure:\n";

try {
    $columns = DB::select("DESCRIBE user_interactions");
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type}" . ($column->Null === 'YES' ? ' (nullable)' : ' (required)') . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Failed to describe table: " . $e->getMessage() . "\n";
}

echo "\n📊 Recent interactions:\n";
try {
    $recent = DB::table('user_interactions')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get(['id', 'user_id', 'business_id', 'interaction_type', 'source', 'created_at']);
    
    foreach ($recent as $interaction) {
        echo "ID {$interaction->id}: User {$interaction->user_id} -> Business {$interaction->business_id} ({$interaction->interaction_type}) from {$interaction->source} at {$interaction->created_at}\n";
    }
} catch (\Exception $e) {
    echo "❌ Failed to get recent interactions: " . $e->getMessage() . "\n";
}
