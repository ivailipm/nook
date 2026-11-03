<?php

namespace App\Actions;

use App\Enums\SubscriptionCycle;
use App\Models\Subscription;
use Carbon\CarbonInterface;

/**
 * The daily roll for every user: a live subscription whose date has passed moves on by its cycle until the date is
 * today or later, and an unanswered trial turns paid in the same run. Cancelled ones stay exactly as they are.
 */
class RollSubscriptions
{
    /**
     * @return array{converted: int, moved: int}
     */
    public function handle(): array
    {
        $subscriptions = Subscription::whereNull('cancelled_at')
            ->where('next_payment_date', '<', today()->toDateString())
            ->get();

        $converted = $subscriptions->where('is_trial', true)->count();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['is_trial' => false, 'next_payment_date' => $this->nextDate($subscription)]);
        }

        return ['converted' => $converted, 'moved' => $subscriptions->count()];
    }

    /**
     * Each step starts from the last one's result, so a clamped month end stays clamped: 31 Jan, 28 Feb, 28 Mar.
     */
    private function nextDate(Subscription $subscription): CarbonInterface
    {
        $date = $subscription->next_payment_date->copy();

        do {
            $date = $subscription->cycle === SubscriptionCycle::Yearly ? $date->addYearNoOverflow() : $date->addMonthNoOverflow();
        } while ($date->lessThan(today()));

        return $date;
    }
}
