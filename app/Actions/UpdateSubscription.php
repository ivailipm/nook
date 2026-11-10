<?php

namespace App\Actions;

use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Models\Subscription;

/**
 * Saves the subscription sheet's validated fields onto the subscription. It never touches the cancelled date or the reminder,
 * and a past date stays as typed until nook:roll-subscriptions moves it on.
 */
class UpdateSubscription
{
    public function handle(Subscription $subscription, string $name, string $price, SubscriptionCycle $cycle, SubscriptionCategory $category, string $nextPaymentDate, bool $isTrial): Subscription
    {
        $subscription->update([
            'name' => $name,
            'price' => $price,
            'cycle' => $cycle,
            'next_payment_date' => $nextPaymentDate,
            'category' => $category,
            'is_trial' => $isTrial,
        ]);

        return $subscription;
    }

    /**
     * The toast after saving.
     */
    public function message(Subscription $subscription): string
    {
        return "{$subscription->name} saved";
    }
}
