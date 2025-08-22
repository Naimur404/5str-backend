<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Main Categories (Level 1)
        $mainCategories = [
            [
                'name' => 'Restaurants',
                'slug' => 'restaurants',
                'level' => 1,
                'icon_image' => 'icons/restaurant.svg',
                'color_code' => '#FF6B6B',
                'is_featured' => true,
                'is_popular' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Shopping',
                'slug' => 'shopping',
                'level' => 1,
                'icon_image' => 'icons/shopping.svg',
                'color_code' => '#4ECDC4',
                'is_featured' => true,
                'is_popular' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Services',
                'slug' => 'services',
                'level' => 1,
                'icon_image' => 'icons/services.svg',
                'color_code' => '#45B7D1',
                'is_featured' => true,
                'is_popular' => true,
                'sort_order' => 3
            ],
            [
                'name' => 'Entertainment',
                'slug' => 'entertainment',
                'level' => 1,
                'icon_image' => 'icons/entertainment.svg',
                'color_code' => '#F7DC6F',
                'is_featured' => true,
                'sort_order' => 4
            ],
            [
                'name' => 'Health & Wellness',
                'slug' => 'health-wellness',
                'level' => 1,
                'icon_image' => 'icons/health.svg',
                'color_code' => '#BB8FCE',
                'is_featured' => true,
                'sort_order' => 5
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'level' => 1,
                'icon_image' => 'icons/education.svg',
                'color_code' => '#85C1E9',
                'sort_order' => 6
            ]
        ];

        foreach ($mainCategories as $categoryData) {
            $category = Category::create($categoryData);
            
            // Add subcategories based on main category
            $this->createSubcategories($category);
        }
    }

    private function createSubcategories($parentCategory)
    {
        $subcategories = [];

        switch ($parentCategory->slug) {
            case 'restaurants':
                $subcategories = [
                    ['name' => 'Pizza', 'slug' => 'pizza', 'is_popular' => true],
                    ['name' => 'Burger', 'slug' => 'burger', 'is_popular' => true],
                    ['name' => 'Chinese', 'slug' => 'chinese', 'is_popular' => true],
                    ['name' => 'Bengali', 'slug' => 'bengali', 'is_popular' => true],
                    ['name' => 'Fast Food', 'slug' => 'fast-food'],
                    ['name' => 'Fine Dining', 'slug' => 'fine-dining'],
                    ['name' => 'Cafe', 'slug' => 'cafe'],
                    ['name' => 'Bakery', 'slug' => 'bakery'],
                ];
                break;

            case 'shopping':
                $subcategories = [
                    ['name' => 'Clothing', 'slug' => 'clothing', 'is_popular' => true],
                    ['name' => 'Electronics', 'slug' => 'electronics', 'is_popular' => true],
                    ['name' => 'Grocery', 'slug' => 'grocery', 'is_popular' => true],
                    ['name' => 'Home & Garden', 'slug' => 'home-garden'],
                    ['name' => 'Books & Stationery', 'slug' => 'books-stationery'],
                    ['name' => 'Sports & Fitness', 'slug' => 'sports-fitness'],
                    ['name' => 'Beauty & Personal Care', 'slug' => 'beauty-personal-care'],
                ];
                break;

            case 'services':
                $subcategories = [
                    ['name' => 'Beauty Salon', 'slug' => 'beauty-salon', 'is_popular' => true],
                    ['name' => 'Car Service', 'slug' => 'car-service', 'is_popular' => true],
                    ['name' => 'Home Repair', 'slug' => 'home-repair'],
                    ['name' => 'Cleaning Service', 'slug' => 'cleaning-service'],
                    ['name' => 'Legal Service', 'slug' => 'legal-service'],
                    ['name' => 'Financial Service', 'slug' => 'financial-service'],
                    ['name' => 'Photography', 'slug' => 'photography'],
                ];
                break;

            case 'entertainment':
                $subcategories = [
                    ['name' => 'Movie Theater', 'slug' => 'movie-theater', 'is_popular' => true],
                    ['name' => 'Gaming Zone', 'slug' => 'gaming-zone'],
                    ['name' => 'Sports Club', 'slug' => 'sports-club'],
                    ['name' => 'Music & Events', 'slug' => 'music-events'],
                    ['name' => 'Amusement Park', 'slug' => 'amusement-park'],
                ];
                break;

            case 'health-wellness':
                $subcategories = [
                    ['name' => 'Hospital', 'slug' => 'hospital', 'is_popular' => true],
                    ['name' => 'Pharmacy', 'slug' => 'pharmacy', 'is_popular' => true],
                    ['name' => 'Dental Care', 'slug' => 'dental-care'],
                    ['name' => 'Gym & Fitness', 'slug' => 'gym-fitness'],
                    ['name' => 'Spa & Massage', 'slug' => 'spa-massage'],
                ];
                break;

            case 'education':
                $subcategories = [
                    ['name' => 'School', 'slug' => 'school'],
                    ['name' => 'University', 'slug' => 'university'],
                    ['name' => 'Coaching Center', 'slug' => 'coaching-center'],
                    ['name' => 'Language Institute', 'slug' => 'language-institute'],
                    ['name' => 'Skill Development', 'slug' => 'skill-development'],
                ];
                break;
        }

        foreach ($subcategories as $subcat) {
            $subcategoryData = array_merge($subcat, [
                'parent_id' => $parentCategory->id,
                'level' => 2,
                'icon_image' => 'icons/subcategory.svg',
                'color_code' => $parentCategory->color_code,
            ]);
            
            $subcategory = Category::create($subcategoryData);
            
            // Create some sub-sub categories for popular ones
            if (isset($subcat['is_popular']) && $subcat['is_popular']) {
                $this->createSubSubcategories($subcategory);
            }
        }
    }

    private function createSubSubcategories($parentSubcategory)
    {
        $subSubcategories = [];

        switch ($parentSubcategory->slug) {
            case 'pizza':
                $subSubcategories = [
                    ['name' => 'Italian Pizza', 'slug' => 'italian-pizza'],
                    ['name' => 'Thin Crust', 'slug' => 'thin-crust'],
                    ['name' => 'Deep Dish', 'slug' => 'deep-dish'],
                ];
                break;

            case 'clothing':
                $subSubcategories = [
                    ['name' => "Men's Clothing", 'slug' => 'mens-clothing'],
                    ['name' => "Women's Clothing", 'slug' => 'womens-clothing'],
                    ['name' => "Kids' Clothing", 'slug' => 'kids-clothing'],
                ];
                break;

            case 'beauty-salon':
                $subSubcategories = [
                    ['name' => 'Hair Cut', 'slug' => 'hair-cut'],
                    ['name' => 'Hair Color', 'slug' => 'hair-color'],
                    ['name' => 'Facial', 'slug' => 'facial'],
                ];
                break;

            case 'movie-theater':
                $subSubcategories = [
                    ['name' => 'IMAX', 'slug' => 'imax'],
                    ['name' => '3D Movies', 'slug' => '3d-movies'],
                    ['name' => 'Regular Screen', 'slug' => 'regular-screen'],
                ];
                break;
        }

        foreach ($subSubcategories as $subSubcat) {
            $subSubcategoryData = array_merge($subSubcat, [
                'parent_id' => $parentSubcategory->id,
                'level' => 3,
                'icon_image' => 'icons/sub-subcategory.svg',
                'color_code' => $parentSubcategory->color_code,
            ]);
            
            Category::create($subSubcategoryData);
        }
    }
}
