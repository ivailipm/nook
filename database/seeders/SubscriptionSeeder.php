<?php

namespace Database\Seeders;

use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    /**
     * Seed the prototype's sample subscriptions for the user, unless they already have subscriptions.
     */
    public function run(User $user): void
    {
        if ($user->subscriptions()->exists()) {
            return;
        }

        $day = fn (int $offset) => today()->addDays($offset);

        $user->subscriptions()->createMany([
            ['name' => 'Gym', 'price' => 24.99, 'cycle' => SubscriptionCycle::Monthly, 'next_payment_date' => $day(3), 'category' => SubscriptionCategory::Health, 'is_trial' => false, 'trial_reminder' => false, 'cancelled_at' => null],
            ['name' => 'Netflix', 'price' => 13.49, 'cycle' => SubscriptionCycle::Monthly, 'next_payment_date' => $day(5), 'category' => SubscriptionCategory::Entertainment, 'is_trial' => false, 'trial_reminder' => false, 'cancelled_at' => null],
            ['name' => 'Spotify', 'price' => 6.49, 'cycle' => SubscriptionCycle::Monthly, 'next_payment_date' => $day(2), 'category' => SubscriptionCategory::Entertainment, 'is_trial' => false, 'trial_reminder' => false, 'cancelled_at' => null],
            ['name' => 'iCloud+', 'price' => 2.99, 'cycle' => SubscriptionCycle::Monthly, 'next_payment_date' => $day(16), 'category' => SubscriptionCategory::Storage, 'is_trial' => false, 'trial_reminder' => false, 'cancelled_at' => null],
            ['name' => 'Duolingo Super', 'price' => 12.99, 'cycle' => SubscriptionCycle::Monthly, 'next_payment_date' => $day(4), 'category' => SubscriptionCategory::Education, 'is_trial' => true, 'trial_reminder' => false, 'cancelled_at' => null],
            ['name' => 'Disney+', 'price' => 7.99, 'cycle' => SubscriptionCycle::Monthly, 'next_payment_date' => $day(0), 'category' => SubscriptionCategory::Entertainment, 'is_trial' => false, 'trial_reminder' => false, 'cancelled_at' => $day(-61)],
        ]);
    }
}
