<?php

namespace App\Support;

use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the Home page shows, all read through the user's relations.
 */
final readonly class HomeDashboard
{
    private const array WORDS = [2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine'];

    /**
     * @param  Collection<int, Task>  $todayTasks  Open ones first.
     * @param  Collection<int, GroceryItem>  $groceries  The first open items, with their promo loaded.
     * @param  Collection<int, Promo>  $soonPromos  Soonest end first, each with "linked".
     * @param  Collection<int, Subscription>  $comingUp  Soonest date first.
     */
    public function __construct(
        public Collection $todayTasks,
        public int $todayLeft,
        public Collection $groceries,
        public int $toBuy,
        public Collection $soonPromos,
        public int $dealsEnding,
        public float $monthly,
        public Collection $comingUp,
    ) {}

    public static function for(User $user): self
    {
        // Due today or earlier, leaving out what was completed on an earlier day.
        $todayTasks = $user->tasks()
            ->where('due_date', '<=', today()->toDateString())
            ->where(fn (Builder $query) => $query->where('done', false)->orWhere('completed_at', '>=', today()))
            ->orderBy('done')
            ->orderBy('id')
            ->get();

        return new self(
            todayTasks: $todayTasks,
            todayLeft: $todayTasks->where('done', false)->count(),
            groceries: $user->groceryItems()->where('done', false)->with('promo')->orderBy('id')->limit(5)->get(),
            toBuy: $user->groceryItems()->where('done', false)->count(),
            // A promo is "on list" while one of the user's open items links to it.
            soonPromos: $user->promos()->endingSoon()
                ->withExists(['groceryItems as linked' => fn (Builder $query) => $query->where('done', false)->where('user_id', $user->id)])
                ->orderBy('valid_until')
                ->orderBy('id')
                ->limit(5)
                ->get(),
            dealsEnding: $user->promos()->endingSoon()->count(),
            monthly: SubscriptionTotals::for($user)->monthly,
            comingUp: $user->subscriptions()
                ->whereNull('cancelled_at')
                ->whereBetween('next_payment_date', [today()->toDateString(), today()->addDays(14)->toDateString()])
                ->orderBy('next_payment_date')
                ->orderBy('id')
                ->limit(4)
                ->get(),
        );
    }

    /**
     * "Good morning" from 05:00, "Good afternoon" from 12:00 and "Good evening" from 18:00, in the app's timezone.
     */
    public static function greeting(CarbonInterface $at): string
    {
        return match (self::partOfDay($at)) {
            'morning' => 'Good morning',
            'afternoon' => 'Good afternoon',
            'evening' => 'Good evening',
        };
    }

    /**
     * The handwritten line under the greeting, with the number in words up to nine. The all-clear names the same part
     * of the day as the greeting.
     */
    public static function todayLine(int $open, CarbonInterface $at): string
    {
        return match ($open) {
            0 => match (self::partOfDay($at)) {
                'morning' => 'all clear today. go enjoy your morning',
                'afternoon' => 'all clear today. go enjoy your afternoon',
                'evening' => 'all clear today. go enjoy your evening',
            },
            1 => 'one small thing and you’re done',
            default => (self::WORDS[$open] ?? $open).' small things and you’re done',
        };
    }

    /**
     * Morning from 05:00, afternoon from 12:00 and evening from 18:00 to 04:59, read in the app's timezone on a copy.
     *
     * @return 'morning'|'afternoon'|'evening'
     */
    private static function partOfDay(CarbonInterface $at): string
    {
        $hour = $at->copy()->setTimezone(config()->string('app.timezone'))->hour;

        return match (true) {
            $hour >= 5 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 18 => 'afternoon',
            default => 'evening',
        };
    }
}
