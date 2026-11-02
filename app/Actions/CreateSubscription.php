<?php

namespace App\Actions;

use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Models\Subscription;
use App\Models\User;

/**
 * Adds a live subscription for the user from the add-subscription sheet's validated fields.
 */
class CreateSubscription
{
    public function handle(User $user, string $name, string $price, SubscriptionCycle $cycle, SubscriptionCategory $category, string $nextPaymentDate, bool $isTrial): Subscription
    {
        return $user->subscriptions()->create([
            'name' => $name,
            'price' => $price,
            'cycle' => $cycle,
            'next_payment_date' => $nextPaymentDate,
            'category' => $category,
            'is_trial' => $isTrial,
            'trial_reminder' => false,
            'cancelled_at' => null,
        ]);
    }

    /**
     * The toast after saving.
     */
    public function message(Subscription $subscription): string
    {
        return $subscription->is_trial
            ? 'Trial saved. You will get a reminder before it turns paid'
            : 'Subscription saved';
    }
}
