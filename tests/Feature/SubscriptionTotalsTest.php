<?php

use App\Actions\RollSubscriptions;
use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Livewire\Pages\Subscriptions;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Money;
use App\Support\RelativeDate;
use App\Support\SubscriptionTotals;
use Database\Seeders\SubscriptionSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function subscriptionFor(User $user, float $price, SubscriptionCategory $category = SubscriptionCategory::Other, array $attributes = []): Subscription
{
    return Subscription::factory()->for($user)->create(['price' => $price, 'category' => $category, ...$attributes]);
}

/**
 * The breakdown as [category, share, printed amount] rows.
 *
 * @return list<array{SubscriptionCategory, int, string}>
 */
function categoryRows(SubscriptionTotals $totals): array
{
    return array_map(
        fn (array $entry) => [$entry['category'], $entry['share'], Money::format($entry['amount'])],
        $totals->categories,
    );
}

it('totals the prototype\'s subscriptions', function () {
    app(SubscriptionSeeder::class)->run($this->user);

    $totals = SubscriptionTotals::for($this->user);

    expect(Money::format($totals->monthly))->toBe('€47.96')
        ->and(Money::format($totals->yearly))->toBe('€575.52')
        ->and(categoryRows($totals))->toBe([
            [SubscriptionCategory::Health, 52, '€24.99'],
            [SubscriptionCategory::Entertainment, 42, '€19.98'],
            [SubscriptionCategory::Storage, 6, '€2.99'],
        ])
        ->and(Money::format($totals->saved))->toBe('€15.98')
        ->and($totals->savingsLine)->toBe('saved €15.98 since cancelling Disney+');
});

it('leaves trials and cancelled subscriptions out of the totals and the categories', function () {
    subscriptionFor($this->user, 10.00, SubscriptionCategory::Health);
    subscriptionFor($this->user, 12.99, SubscriptionCategory::Education, ['is_trial' => true]);
    subscriptionFor($this->user, 7.99, SubscriptionCategory::Storage, ['cancelled_at' => today()]);
    subscriptionFor($this->user, 5.00, SubscriptionCategory::Health, ['cancelled_at' => today()]);

    $totals = SubscriptionTotals::for($this->user);

    expect(Money::format($totals->monthly))->toBe('€10.00')
        ->and(Money::format($totals->yearly))->toBe('€120.00')
        ->and(categoryRows($totals))->toBe([[SubscriptionCategory::Health, 100, '€10.00']]);
});

it('is all zero with nothing paying and nothing cancelled', function (bool $trial) {
    if ($trial) {
        subscriptionFor($this->user, 12.99, SubscriptionCategory::Education, ['is_trial' => true]);
    }

    $totals = SubscriptionTotals::for($this->user);

    expect($totals->monthly)->toBe(0.0)
        ->and(Money::format($totals->monthly))->toBe('€0.00')
        ->and(Money::format($totals->yearly))->toBe('€0.00')
        ->and($totals->categories)->toBe([])
        ->and($totals->saved)->toBe(0.0)
        ->and($totals->savingsLine)->toBeNull();
})->with(['only a trial' => true, 'no subscriptions' => false]);

it('counts a yearly subscription at a twelfth of its price', function () {
    subscriptionFor($this->user, 120.00, SubscriptionCategory::Storage, ['cycle' => SubscriptionCycle::Yearly]);
    subscriptionFor($this->user, 5.00, SubscriptionCategory::Health);

    $totals = SubscriptionTotals::for($this->user);

    expect(Money::format($totals->monthly))->toBe('€15.00')
        ->and(Money::format($totals->yearly))->toBe('€180.00')
        ->and(categoryRows($totals))->toBe([
            [SubscriptionCategory::Storage, 67, '€10.00'],
            [SubscriptionCategory::Health, 33, '€5.00'],
        ]);
});

it('rounds only when printing, so the yearly total isn\'t the rounded month times 12', function () {
    subscriptionFor($this->user, 49.99, SubscriptionCategory::Storage, ['cycle' => SubscriptionCycle::Yearly]);
    subscriptionFor($this->user, 10.00, SubscriptionCategory::Health);

    $totals = SubscriptionTotals::for($this->user);

    expect(Money::format($totals->monthly))->toBe('€14.17')
        ->and(Money::format($totals->yearly))->toBe('€169.99');
});

it('sorts the categories by amount, keeping first-seen order on a tie', function () {
    subscriptionFor($this->user, 5.00, SubscriptionCategory::Storage);
    subscriptionFor($this->user, 5.00, SubscriptionCategory::Health);
    subscriptionFor($this->user, 10.00, SubscriptionCategory::Other);

    expect(categoryRows(SubscriptionTotals::for($this->user)))->toBe([
        [SubscriptionCategory::Other, 50, '€10.00'],
        [SubscriptionCategory::Storage, 25, '€5.00'],
        [SubscriptionCategory::Health, 25, '€5.00'],
    ]);
});

it('sums two subscriptions in one category into one entry', function () {
    subscriptionFor($this->user, 6.49, SubscriptionCategory::Entertainment);
    subscriptionFor($this->user, 10.00, SubscriptionCategory::Health);
    subscriptionFor($this->user, 13.49, SubscriptionCategory::Entertainment);

    expect(categoryRows(SubscriptionTotals::for($this->user)))->toBe([
        [SubscriptionCategory::Entertainment, 67, '€19.98'],
        [SubscriptionCategory::Health, 33, '€10.00'],
    ]);
});

it('doesn\'t force the shares to add up to 100', function () {
    subscriptionFor($this->user, 10.00, SubscriptionCategory::Health);
    subscriptionFor($this->user, 10.00, SubscriptionCategory::Storage);
    subscriptionFor($this->user, 10.00, SubscriptionCategory::Other);

    expect(categoryRows(SubscriptionTotals::for($this->user)))->toBe([
        [SubscriptionCategory::Health, 33, '€10.00'],
        [SubscriptionCategory::Storage, 33, '€10.00'],
        [SubscriptionCategory::Other, 33, '€10.00'],
    ]);
});

it('gives a free paying subscription a share of 0 without dividing by zero', function () {
    subscriptionFor($this->user, 0.00, SubscriptionCategory::Health);

    $totals = SubscriptionTotals::for($this->user);

    expect(Money::format($totals->monthly))->toBe('€0.00')
        ->and(categoryRows($totals))->toBe([[SubscriptionCategory::Health, 0, '€0.00']]);
});

it('counts the months since cancelling', function (string $cancelledAt, int $days, string $saved) {
    $subscription = subscriptionFor($this->user, 10.00, attributes: ['name' => 'Gym', 'cancelled_at' => $cancelledAt])->fresh();

    $totals = SubscriptionTotals::for($this->user);

    expect(RelativeDate::daysFromToday($subscription->cancelled_at))->toBe(-$days)
        ->and(Money::format($subscription->savedAmount()))->toBe($saved)
        ->and(Money::format($totals->saved))->toBe($saved)
        ->and($totals->savingsLine)->toBe("saved {$saved} since cancelling Gym");
})->with([
    'cancelled today, the minimum' => ['2026-10-29', 0, '€10.00'],
    'yesterday' => ['2026-10-28', 1, '€10.00'],
    '14 days rounds to 0, so the minimum' => ['2026-10-15', 14, '€10.00'],
    '30 days' => ['2026-09-29', 30, '€10.00'],
    '44 days rounds down, across the 25 Oct clock change' => ['2026-09-15', 44, '€10.00'],
    '45 days, a half rounds up, across the clock change' => ['2026-09-14', 45, '€20.00'],
    '61 days, the prototype\'s Disney+' => ['2026-08-29', 61, '€20.00'],
    '75 days' => ['2026-08-15', 75, '€30.00'],
    'a year is 12 months, not calendar months' => ['2025-10-29', 365, '€120.00'],
]);

it('saves a twelfth of a yearly price for every month', function () {
    subscriptionFor($this->user, 120.00, attributes: ['name' => 'Gym', 'cycle' => SubscriptionCycle::Yearly, 'cancelled_at' => today()->subDays(61)]);

    expect(SubscriptionTotals::for($this->user)->savingsLine)->toBe('saved €20.00 since cancelling Gym');
});

it('leaves a cancelled trial out of the savings', function () {
    $trial = subscriptionFor($this->user, 12.99, SubscriptionCategory::Education, ['name' => 'Duolingo Super', 'is_trial' => true, 'cancelled_at' => today()])->fresh();

    $totals = SubscriptionTotals::for($this->user);

    expect($trial->savedAmount())->toBe(0.0)
        ->and($totals->saved)->toBe(0.0)
        ->and($totals->savingsLine)->toBeNull()
        ->and($totals->monthly)->toBe(0.0)
        ->and($totals->categories)->toBe([]);
});

it('names only the paid subscription beside a cancelled trial', function () {
    subscriptionFor($this->user, 12.99, SubscriptionCategory::Education, ['name' => 'Duolingo Super', 'is_trial' => true, 'cancelled_at' => today()]);
    subscriptionFor($this->user, 7.99, attributes: ['name' => 'Disney+', 'cancelled_at' => today()]);

    $totals = SubscriptionTotals::for($this->user);

    expect($totals->saved)->toBe(7.99)
        ->and($totals->savingsLine)->toBe('saved €7.99 since cancelling Disney+');
});

it('counts a trial turned paid and cancelled later', function (Closure $turnPaid) {
    $this->actingAs($this->user);
    $trial = subscriptionFor($this->user, 12.99, SubscriptionCategory::Education, ['name' => 'Duolingo Super', 'is_trial' => true, 'next_payment_date' => '2026-10-28']);

    $turnPaid($trial);

    expect($trial->fresh()->is_trial)->toBeFalse();

    Livewire::test(Subscriptions::class)->call('cancel', $trial->id);

    $totals = SubscriptionTotals::for($this->user);

    expect($trial->fresh()->savedAmount())->toBe(12.99)
        ->and($totals->saved)->toBe(12.99)
        ->and($totals->savingsLine)->toBe('saved €12.99 since cancelling Duolingo Super');
})->with([
    'by Keep it' => fn (Subscription $trial) => Livewire::test(Subscriptions::class)->call('keepTrial', $trial->id),
    'by the job' => fn () => app(RollSubscriptions::class)->handle(),
]);

it('adds up the savings and names the subscriptions in id order, not by cancel date', function () {
    subscriptionFor($this->user, 7.99, attributes: ['name' => 'Disney+', 'cancelled_at' => today()]);
    subscriptionFor($this->user, 12.00, attributes: ['name' => 'HBO', 'cancelled_at' => today()->subDays(61)]);

    expect(SubscriptionTotals::for($this->user)->savingsLine)->toBe('saved €31.99 since cancelling Disney+, HBO');
});

it('never reads the next payment date', function () {
    $other = User::factory()->create();

    foreach ([[$this->user, -40], [$other, 40]] as [$user, $offset]) {
        subscriptionFor($user, 10.00, SubscriptionCategory::Health, ['next_payment_date' => today()->addDays($offset)]);
        subscriptionFor($user, 12.99, SubscriptionCategory::Education, ['is_trial' => true, 'next_payment_date' => today()->addDays($offset)]);
    }

    $past = SubscriptionTotals::for($this->user);

    expect(Money::format($past->monthly))->toBe('€10.00')
        ->and($past)->toEqual(SubscriptionTotals::for($other));
});

it('ignores other users\' subscriptions', function () {
    subscriptionFor($this->user, 10.00, SubscriptionCategory::Health, ['name' => 'Gym', 'cancelled_at' => null]);
    subscriptionFor($this->user, 5.00, attributes: ['name' => 'Disney+', 'cancelled_at' => today()]);

    $before = SubscriptionTotals::for($this->user);

    $other = User::factory()->create();
    subscriptionFor($other, 99.00, SubscriptionCategory::Storage);
    subscriptionFor($other, 50.00, SubscriptionCategory::Health, ['name' => 'HBO', 'cancelled_at' => today()]);

    $after = SubscriptionTotals::for($this->user);

    expect($after)->toEqual($before)
        ->and(Money::format($after->monthly))->toBe('€10.00')
        ->and($after->savingsLine)->toBe('saved €5.00 since cancelling Disney+');
});
