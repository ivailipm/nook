<?php

namespace Database\Factories;

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GroceryItem>
 */
class GroceryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => Str::ucfirst(fake()->word()),
            'qty' => null,
            'store' => fake()->randomElement(Store::cases())->value,
            'category' => fake()->randomElement(GroceryCategory::cases()),
            'done' => false,
            'promo_id' => null,
        ];
    }
}
