<?php

namespace Database\Factories;

use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state: a paying monthly subscription.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => Str::ucfirst(fake()->word()),
            'price' => fake()->randomFloat(2, 1, 30),
            'cycle' => SubscriptionCycle::Monthly,
            'next_payment_date' => today()->addDays(fake()->numberBetween(1, 30)),
            'category' => fake()->randomElement(SubscriptionCategory::cases()),
            'is_trial' => false,
            'trial_reminder' => false,
            'cancelled_at' => null,
        ];
    }
}
