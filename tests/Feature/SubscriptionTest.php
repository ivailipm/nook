<?php

use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Models\Subscription;
use App\Models\User;
use App\Support\RelativeDate;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));
});

it('persists a factory subscription as a paying monthly one for a user', function () {
    $subscription = Subscription::factory()->create()->fresh();

    $this->assertModelExists($subscription);

    expect($subscription->user->is(User::sole()))->toBeTrue()
        ->and($subscription->name)->not->toBe('')
        ->and((float) $subscription->price)->toBeGreaterThanOrEqual(1.0)->toBeLessThanOrEqual(30.0)
        ->and($subscription->price)->toMatch('/^\d+\.\d{2}$/')
        ->and($subscription->cycle)->toBe(SubscriptionCycle::Monthly)
        ->and(RelativeDate::daysFromToday($subscription->next_payment_date))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(30)
        ->and($subscription->category)->toBeInstanceOf(SubscriptionCategory::class)
        ->and($subscription->is_trial)->toBeFalse()
        ->and($subscription->trial_reminder)->toBeFalse()
        ->and($subscription->cancelled_at)->toBeNull();
});

it('casts its columns when read back', function () {
    $subscription = Subscription::factory()->create([
        'price' => 24.99,
        'cycle' => 'yearly',
        'next_payment_date' => '2026-11-01',
        'category' => 'Health',
        'is_trial' => true,
        'trial_reminder' => true,
        'cancelled_at' => '2026-10-28',
    ])->fresh();

    expect($subscription->price)->toBe('24.99')
        ->and($subscription->cycle)->toBe(SubscriptionCycle::Yearly)
        ->and($subscription->category)->toBe(SubscriptionCategory::Health)
        ->and($subscription->next_payment_date)->toBeInstanceOf(Carbon::class)
        ->and($subscription->next_payment_date->toDateString())->toBe('2026-11-01')
        ->and($subscription->cancelled_at)->toBeInstanceOf(Carbon::class)
        ->and($subscription->cancelled_at->toDateString())->toBe('2026-10-28')
        ->and($subscription->is_trial)->toBeTrue()
        ->and($subscription->trial_reminder)->toBeTrue();
});

it('gives the monthly price of a monthly and a yearly subscription', function () {
    $monthly = Subscription::factory()->create(['price' => 24.99])->fresh();
    $yearly = Subscription::factory()->create(['price' => 120.00, 'cycle' => SubscriptionCycle::Yearly])->fresh();
    $unrounded = Subscription::factory()->create(['price' => 49.99, 'cycle' => SubscriptionCycle::Yearly])->fresh();

    expect($monthly->monthlyPrice())->toBe(24.99)
        ->and($yearly->monthlyPrice())->toBe(10.0)
        ->and($unrounded->monthlyPrice())->toEqualWithDelta(4.1658, 0.0001);
});

it('tells paying, trial and cancelled subscriptions apart', function (bool $trial, ?string $cancelledAt, bool $cancelled, bool $paying) {
    $subscription = Subscription::factory()->create(['is_trial' => $trial, 'cancelled_at' => $cancelledAt])->fresh();

    expect($subscription->isCancelled())->toBe($cancelled)
        ->and($subscription->isPaying())->toBe($paying);
})->with([
    'paying' => [false, null, false, true],
    'on trial' => [true, null, false, false],
    'cancelled' => [false, '2026-10-28', true, false],
    'a cancelled trial' => [true, '2026-10-28', true, false],
]);

it('has saved nothing while it isn\'t cancelled', function (bool $trial) {
    $subscription = Subscription::factory()->create(['is_trial' => $trial, 'price' => 10.00])->fresh();

    expect($subscription->savedAmount())->toBe(0.0);
})->with(['paying' => false, 'on trial' => true]);

it('lists only the user\'s own subscriptions', function () {
    $user = User::factory()->create();
    $subscriptions = Subscription::factory()->count(2)->for($user)->create();
    Subscription::factory()->create();

    expect($user->subscriptions->modelKeys())->toEqualCanonicalizing($subscriptions->modelKeys());
});

it('deletes the user\'s subscriptions with the user', function () {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create();
    $other = Subscription::factory()->create();

    $user->delete();

    expect(Subscription::pluck('id')->all())->toBe([$other->id]);
});
