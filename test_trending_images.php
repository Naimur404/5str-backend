<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Today Trending API with Images\n";
echo "=====================================\n\n";

try {
    $controller = app('App\Http\Controllers\Api\HomeController');
    $request = new Illuminate\Http\Request([
        'latitude' => 23.750,
        'longitude' => 90.375,
        'business_limit' => 2,
        'offering_limit' => 2
    ]);

    $response = $controller->todayTrending($request);
    $data = json_decode($response->getContent(), true);

    echo "API Response Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";

    if ($data['success']) {
        echo "Date: " . $data['data']['summary']['date'] . "\n";
        echo "Area: " . $data['data']['summary']['area'] . "\n";
        echo "Businesses Count: " . count($data['data']['trending_businesses']) . "\n";
        echo "Offerings Count: " . count($data['data']['trending_offerings']) . "\n\n";

        // Test business images
        if (count($data['data']['trending_businesses']) > 0) {
            echo "BUSINESS IMAGE DATA:\n";
            echo "===================\n";
            foreach ($data['data']['trending_businesses'] as $index => $business) {
                echo "Business " . ($index + 1) . ": " . $business['business_name'] . "\n";
                echo "  - Has images object: " . (isset($business['images']) ? 'Yes' : 'No') . "\n";
                if (isset($business['images'])) {
                    echo "  - Logo: " . ($business['images']['logo'] ?? 'null') . "\n";
                    echo "  - Cover: " . ($business['images']['cover'] ?? 'null') . "\n";
                    echo "  - Gallery count: " . count($business['images']['gallery']) . "\n";
                }
                echo "\n";
            }
        }

        // Test offering images
        if (count($data['data']['trending_offerings']) > 0) {
            echo "OFFERING IMAGE DATA:\n";
            echo "===================\n";
            foreach ($data['data']['trending_offerings'] as $index => $offering) {
                echo "Offering " . ($index + 1) . ": " . $offering['name'] . "\n";
                echo "  - Image URL: " . ($offering['image_url'] ?? 'null') . "\n";
                echo "  - Business: " . $offering['business']['business_name'] . "\n";
                echo "  - Business Logo: " . ($offering['business']['images']['logo'] ?? 'null') . "\n";
                echo "  - Business Cover: " . ($offering['business']['images']['cover'] ?? 'null') . "\n";
                echo "\n";
            }
        }
    } else {
        echo "Error: " . $data['message'] . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
