<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserCollection>
 */
class UserCollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        
        return [
            'user_id' => User::factory(),
            'name' => ucwords($name),
            'description' => $this->faker->sentence(10),
            'is_public' => $this->faker->boolean(70), // 70% chance of being public
            'cover_image' => $this->faker->imageUrl(400, 300, 'business'),
            'slug' => \Illuminate\Support\Str::slug($name . '-' . time()),
        ];
    }

    /**
     * Create a public collection.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Create a private collection.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}
