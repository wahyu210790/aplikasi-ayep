<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => 'Barang '.fake()->words(2, true),
            'barcode' => fake()->unique()->numerify('899##########'),
            'category' => fake()->randomElement(['Sembako', 'Minuman', 'Makanan Ringan', 'Olahan', null]),
            'cost_price' => 10000,
            'supplier_price' => 11000,
            'retail_price' => 12500,
            'processed_price' => 15000,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
