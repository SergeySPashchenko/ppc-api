<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;
use App\Models\Gender;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ProductID' => fake()->unique()->numberBetween(1, 1000000),
            'Product' => fake()->word(),
            'slug' => fake()->slug(),
            'newSystem' => fake()->boolean(),
            'Visible' => fake()->boolean(),
            'flyer' => fake()->boolean(),
            'main_category_id' => Category::factory(),
            'marketing_category_id' => Category::factory(),
            'gender_id' => Gender::factory(),
            //
        ];
    }
}
