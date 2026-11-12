<?php

namespace Database\Factories;

use App\Models\CustomStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomStore>
 */
class CustomStoreFactory extends Factory
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
            'name' => Str::ucfirst(fake()->unique()->word()),
        ];
    }
}
