<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Gender;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'Product' => fake()->words(3, true),
            'newSystem' => fake()->boolean(),
            'Visible' => true,
            'flyer' => fake()->boolean(),
            'brand_id' => Brand::factory(),
            'main_category_id' => Category::factory(),
            'marketing_category_id' => Category::factory(),
            'gender_id' => Gender::factory(),
        ];
    }
}
