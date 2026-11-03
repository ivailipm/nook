<?php

namespace App\Support;

use App\Enums\SubscriptionCategory;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A user's monthly and yearly totals, category breakdown and savings, following the prototype.
 * Totals and the breakdown count paying subscriptions, and savings count cancelled paid ones, not cancelled trials.
 * Amounts stay unrounded until Money::format() prints them.
 */
final readonly class SubscriptionTotals
{
    /**
     * @param  list<array{category: SubscriptionCategory, amount: float, share: int}>  $categories  Largest amount first. Shares aren't adjusted to add up to 100.
     */
    public function __construct(
        public float $monthly,
        public float $yearly,
        public array $categories,
        public float $saved,
        public ?string $savingsLine,
    ) {}

    public static function for(User $user): self
    {
        $subscriptions = $user->subscriptions()->orderBy('id')->get();
        $paying = $subscriptions->filter(fn (Subscription $subscription) => $subscription->isPaying());
        $cancelled = $subscriptions->filter(fn (Subscription $subscription) => $subscription->isCancelled() && ! $subscription->is_trial);

        $monthly = self::sumMonthly($paying);
        $saved = (float) $cancelled->sum(fn (Subscription $subscription) => $subscription->savedAmount());

        $categories = $paying
            ->groupBy(fn (Subscription $subscription) => $subscription->category->value)
            ->map(function (Collection $group, string $category) use ($monthly) {
                $amount = self::sumMonthly($group);

                return [
                    'category' => SubscriptionCategory::from($category),
                    'amount' => $amount,
                    'share' => $monthly > 0 ? (int) round($amount / $monthly * 100) : 0,
                ];
            })
            ->sortByDesc('amount');

        return new self(
            monthly: $monthly,
            yearly: $monthly * 12,
            categories: array_values($categories->all()),
            saved: $saved,
            savingsLine: $cancelled->isEmpty() ? null : 'saved '.Money::format($saved).' since cancelling '.$cancelled->pluck('name')->implode(', '),
        );
    }

    /**
     * @param  Collection<array-key, Subscription>  $subscriptions
     */
    private static function sumMonthly(Collection $subscriptions): float
    {
        return (float) $subscriptions->sum(fn (Subscription $subscription) => $subscription->monthlyPrice());
    }
}
