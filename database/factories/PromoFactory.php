<?php

namespace Database\Factories;

use App\Enums\Store;
use App\Models\Promo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
{
    /**
     * Define the model's default state: an active promo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store' => fake()->randomElement(Store::cases())->value,
            'product' => Str::ucfirst(fake()->word()),
            'deal' => fake()->randomElement(['2+1 free', '1+1', '−30%', '2nd ½ price', '€1.99']),
            'note' => null,
            'valid_from' => today()->subDays(fake()->numberBetween(1, 5)),
            'valid_until' => today()->addDays(fake()->numberBetween(0, 14)),
            'photo_path' => null,
            'tone' => fake()->numberBetween(0, 5),
        ];
    }
}
