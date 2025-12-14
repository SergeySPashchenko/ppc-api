<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'OrderID' => fake()->unique()->numberBetween(1000, 999999),
            'Agent' => fake()->name(),
            'Created' => fake()->dateTime(),
            'OrderDate' => fake()->date(),
            'OrderNum' => fake()->unique()->bothify('ORD-####-???'),
            'ProductTotal' => fake()->randomFloat(2, 10, 1000),
            'GrandTotal' => fake()->randomFloat(2, 10, 1000),
            'RefundAmount' => 0,
            'Shipping' => null,
            'ShippingMethod' => null,
            'Refund' => false,
            'is_marketplace' => false,
            'has_missing_contact_info' => false,
        ];
    }
}
