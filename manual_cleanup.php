<?php
// Manual cleanup script - run with php artisan tinker

// Delete similarities between restaurants and clothing stores
use App\Models\BusinessSimilarity;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

// Find and delete wrong similarities
$wrongCount = BusinessSimilarity::whereHas('businessA.category', function($q) {
    $q->whereIn('name', ['Restaurant', 'Food']);
})->whereHas('businessB.category', function($q) {
    $q->whereIn('name', ['Clothing', 'Fashion', 'Apparel']);
})->delete();

echo "Deleted {$wrongCount} wrong restaurant-clothing similarities\n";

// Delete similarities with very low scores (likely wrong)
$lowScoreCount = BusinessSimilarity::where('similarity_score', '<', 0.1)->delete();
echo "Deleted {$lowScoreCount} very low score similarities\n";

// Find and delete similarities between businesses of different categories with high scores
$crossCategorySimilarities = BusinessSimilarity::with(['businessA', 'businessB'])
    ->get()
    ->filter(function($sim) {
        return $sim->businessA->category_id !== $sim->businessB->category_id && $sim->similarity_score > 0.5;
    });

$crossCategoryCount = $crossCategorySimilarities->count();
$crossCategorySimilarities->each(function($sim) {
    $sim->delete();
});

echo "Deleted {$crossCategoryCount} cross-category high similarities\n";