<?php

namespace Database\Factories;

use App\Models\CustomTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomTag>
 */
class CustomTagFactory extends Factory
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
            'name' => Str::lower(fake()->unique()->lexify('tag????')),
        ];
    }
}
