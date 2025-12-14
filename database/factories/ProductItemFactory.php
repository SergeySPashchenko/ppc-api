<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductItem>
 */
class ProductItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ProductID' => null,
            'ProductName' => fake()->words(3, true),
            'SKU' => fake()->unique()->bothify('SKU-####-???'),
            'Quantity' => fake()->numberBetween(0, 100),
            'upSell' => false,
            'active' => true,
            'deleted' => false,
            'offerProducts' => null,
            'extraProduct' => false,
            'is_valid' => true,
            'is_available' => true,
            'is_discount_item' => false,
            'is_bundle' => false,
        ];
    }
}
