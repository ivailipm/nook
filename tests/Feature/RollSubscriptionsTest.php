<?php

use App\Actions\RollSubscriptions;
use App\Enums\SubscriptionCycle;
use App\Models\Subscription;
use App\Models\User;
use App\Support\HomeDashboard;
use App\Support\Money;
use App\Support\SubscriptionTotals;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function subscriptionDated(string $date, array $attributes = []): Subscription
{
    return Subscription::factory()->create(['next_payment_date' => $date, ...$attributes]);
}

/**
 * Every subscription's raw attributes, with updated_at set back so a write would show.
 *
 * @return list<array<string, mixed>>
 */
function subscriptionSnapshot(): array
{
    Subscription::query()->update(['updated_at' => '2026-10-01 08:00:00']);

    return subscriptionRows();
}

/**
 * @return list<array<string, mixed>>
 */
function subscriptionRows(): array
{
    return Subscription::orderBy('id')->get()->map->getAttributes()->all();
}

// Moving dates

it('moves a passed date on by the cycle', function (string $today, string $before, SubscriptionCycle $cycle, string $after) {
    $this->travelTo("{$today} 00:00");
    $subscription = subscriptionDated($before, ['cycle' => $cycle]);

    app(RollSubscriptions::class)->handle();

    expect($subscription->fresh()->next_payment_date->toDateString())->toBe($after);
})->with([
    'passed yesterday' => ['2026-11-03', '2026-11-02', SubscriptionCycle::Monthly, '2026-12-02'],
    'yearly moves a year' => ['2026-11-03', '2026-10-20', SubscriptionCycle::Yearly, '2027-10-20'],
    'today hasn\'t passed' => ['2026-11-03', '2026-11-03', SubscriptionCycle::Monthly, '2026-11-03'],
    'still ahead' => ['2026-11-03', '2026-11-04', SubscriptionCycle::Monthly, '2026-11-04'],
    'several cycles behind' => ['2026-11-03', '2026-08-15', SubscriptionCycle::Monthly, '2026-11-15'],
    'catching up lands on today and stops' => ['2026-11-03', '2026-08-03', SubscriptionCycle::Monthly, '2026-11-03'],
    'several years behind' => ['2026-11-03', '2024-06-01', SubscriptionCycle::Yearly, '2027-06-01'],
    'no overflow into December' => ['2026-11-01', '2026-10-31', SubscriptionCycle::Monthly, '2026-11-30'],
    'no overflow into March' => ['2027-02-01', '2027-01-31', SubscriptionCycle::Monthly, '2027-02-28'],
    'leap day' => ['2028-03-01', '2028-02-29', SubscriptionCycle::Yearly, '2029-02-28'],
    'a clamped month end stays clamped' => ['2027-03-01', '2027-01-31', SubscriptionCycle::Monthly, '2027-03-28'],
]);

it('counts a date as passed from midnight, whatever the time of day', function (string $time) {
    $this->travelTo("2026-11-03 {$time}");
    $yesterday = subscriptionDated('2026-11-02');
    $today = subscriptionDated('2026-11-03');

    expect(app(RollSubscriptions::class)->handle())->toBe(['converted' => 0, 'moved' => 1])
        ->and($yesterday->fresh()->next_payment_date->toDateString())->toBe('2026-12-02')
        ->and($today->fresh()->next_payment_date->toDateString())->toBe('2026-11-03');
})->with(['just after midnight' => '00:00:01', 'late in the evening' => '23:59:59']);

it('leaves dates of today and later exactly as they were', function () {
    $this->travelTo('2026-11-03 00:00');

    foreach (['2026-11-03', '2026-11-04'] as $date) {
        subscriptionDated($date);
        subscriptionDated($date, ['is_trial' => true]);
    }

    $before = subscriptionSnapshot();

    expect(app(RollSubscriptions::class)->handle())->toBe(['converted' => 0, 'moved' => 0])
        ->and(subscriptionRows())->toBe($before);
});

it('leaves cancelled subscriptions exactly as they were', function () {
    $this->travelTo('2026-11-03 00:00');
    subscriptionDated('2026-10-20', ['cancelled_at' => '2026-10-25']);
    $trial = subscriptionDated('2026-10-20', ['is_trial' => true, 'cancelled_at' => '2026-10-25']);
    $before = subscriptionSnapshot();

    expect(app(RollSubscriptions::class)->handle())->toBe(['converted' => 0, 'moved' => 0])
        ->and(subscriptionRows())->toBe($before)
        ->and($trial->fresh()->is_trial)->toBeTrue();
});

// Trials

it('turns an unanswered trial paid in the run that moves its date', function (string $ended, SubscriptionCycle $cycle, string $price, string $after) {
    $this->travelTo('2026-11-03 00:00');
    $trial = subscriptionDated($ended, ['is_trial' => true, 'cycle' => $cycle, 'price' => $price]);

    expect(app(RollSubscriptions::class)->handle())->toBe(['converted' => 1, 'moved' => 1]);

    $fresh = $trial->fresh();

    expect($fresh->is_trial)->toBeFalse()
        ->and($fresh->isPaying())->toBeTrue()
        ->and($fresh->price)->toBe($price)
        ->and($fresh->cycle)->toBe($cycle)
        ->and($fresh->next_payment_date->toDateString())->toBe($after);
})->with([
    'monthly, ended yesterday' => ['2026-11-02', SubscriptionCycle::Monthly, '12.99', '2026-12-02'],
    'yearly, ended yesterday' => ['2026-11-02', SubscriptionCycle::Yearly, '120.00', '2027-11-02'],
    'several cycles behind' => ['2026-08-15', SubscriptionCycle::Monthly, '12.99', '2026-11-15'],
]);

it('keeps a trial ending today a trial, with the same date', function () {
    $this->travelTo('2026-11-03 00:00');
    $trial = subscriptionDated('2026-11-03', ['is_trial' => true]);

    expect(app(RollSubscriptions::class)->handle())->toBe(['converted' => 0, 'moved' => 0])
        ->and($trial->fresh()->isLiveTrial())->toBeTrue()
        ->and($trial->fresh()->next_payment_date->toDateString())->toBe('2026-11-03');
});

it('leaves the reminder flag alone when a trial turns paid', function (bool $reminder) {
    $this->travelTo('2026-11-03 00:00');
    $trial = subscriptionDated('2026-11-02', ['is_trial' => true, 'trial_reminder' => $reminder]);

    app(RollSubscriptions::class)->handle();

    expect($trial->fresh()->is_trial)->toBeFalse()
        ->and($trial->fresh()->trial_reminder)->toBe($reminder);
})->with(['on' => true, 'off' => false]);

it('moves a paying row without counting it as converted', function () {
    $this->travelTo('2026-11-03 00:00');
    $paying = subscriptionDated('2026-11-02');

    expect(app(RollSubscriptions::class)->handle())->toBe(['converted' => 0, 'moved' => 1])
        ->and($paying->fresh()->is_trial)->toBeFalse()
        ->and($paying->fresh()->next_payment_date->toDateString())->toBe('2026-12-02');
});

it('brings a trial turned paid into the totals and Coming up', function () {
    $this->travelTo('2026-11-03 00:00');
    $user = User::factory()->create();
    $paying = Subscription::factory()->for($user)->create(['price' => 10.00, 'next_payment_date' => '2026-10-10']);
    $trial = Subscription::factory()->for($user)->create(['price' => 12.99, 'next_payment_date' => '2026-10-10', 'is_trial' => true]);

    expect(HomeDashboard::for($user)->comingUp)->toBeEmpty()
        ->and(Money::format(SubscriptionTotals::for($user)->monthly))->toBe('€10.00');

    app(RollSubscriptions::class)->handle();

    $comingUp = HomeDashboard::for($user)->comingUp;

    expect($comingUp->modelKeys())->toBe([$paying->id, $trial->id])
        ->and($comingUp->last()->comingUpLabel())->toBe('pays Tue 10 Nov')
        ->and($comingUp->last()->comingUpAmount())->toBe('€12.99')
        ->and(Money::format(SubscriptionTotals::for($user)->monthly))->toBe('€22.99');
});

// Repeating, users, the command and the schedule

it('changes nothing on a second run the same day', function () {
    $this->travelTo('2026-11-03 00:00');
    subscriptionDated('2026-11-02');
    subscriptionDated('2026-08-15', ['is_trial' => true]);

    expect(app(RollSubscriptions::class)->handle())->toBe(['converted' => 1, 'moved' => 2]);

    $before = subscriptionSnapshot();

    expect(app(RollSubscriptions::class)->handle())->toBe(['converted' => 0, 'moved' => 0])
        ->and(subscriptionRows())->toBe($before);
});

it('moves the subscriptions of every user', function () {
    $this->travelTo('2026-11-03 00:00');
    $mine = Subscription::factory()->for(User::factory())->create(['next_payment_date' => '2026-11-02']);
    $theirs = Subscription::factory()->for(User::factory())->create(['next_payment_date' => '2026-11-01']);

    expect($mine->user_id)->not->toBe($theirs->user_id)
        ->and(app(RollSubscriptions::class)->handle())->toBe(['converted' => 0, 'moved' => 2])
        ->and($mine->fresh()->next_payment_date->toDateString())->toBe('2026-12-02')
        ->and($theirs->fresh()->next_payment_date->toDateString())->toBe('2026-12-01');
});

it('runs the job with nook:roll-subscriptions and prints its counts', function () {
    $this->travelTo('2026-11-03 00:00');
    $trial = subscriptionDated('2026-11-02', ['is_trial' => true]);
    $monthly = subscriptionDated('2026-10-20');
    $yearly = subscriptionDated('2026-10-20', ['cycle' => SubscriptionCycle::Yearly]);
    subscriptionDated('2026-10-20', ['cancelled_at' => '2026-10-25']);
    subscriptionDated('2026-11-03');

    $this->artisan('nook:roll-subscriptions')
        ->expectsOutputToContain('Turned 1 trial paid and moved 3 subscriptions on.')
        ->assertSuccessful();

    expect($trial->fresh()->is_trial)->toBeFalse()
        ->and($trial->fresh()->next_payment_date->toDateString())->toBe('2026-12-02')
        ->and($monthly->fresh()->next_payment_date->toDateString())->toBe('2026-11-20')
        ->and($yearly->fresh()->next_payment_date->toDateString())->toBe('2027-10-20');
});

it('prints the singular and plural of both nouns', function (int $trials, int $paying, string $output) {
    $this->travelTo('2026-11-03 00:00');
    Subscription::factory()->count($trials)->create(['next_payment_date' => '2026-11-02', 'is_trial' => true]);
    Subscription::factory()->count($paying)->create(['next_payment_date' => '2026-11-02']);

    $this->artisan('nook:roll-subscriptions')
        ->expectsOutputToContain($output)
        ->assertSuccessful();
})->with([
    'one of each' => [1, 0, 'Turned 1 trial paid and moved 1 subscription on.'],
    'no trial, one moved' => [0, 1, 'Turned 0 trials paid and moved 1 subscription on.'],
    'two of each' => [2, 0, 'Turned 2 trials paid and moved 2 subscriptions on.'],
]);

it('reports zero counts when there is nothing to do', function () {
    subscriptionDated(today()->toDateString());

    $this->artisan('nook:roll-subscriptions')
        ->expectsOutputToContain('Turned 0 trials paid and moved 0 subscriptions on.')
        ->assertSuccessful();
});

it('schedules nook:roll-subscriptions daily at midnight in the app timezone', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => Str::endsWith((string) $event->command, 'nook:roll-subscriptions'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *')
        ->and($events->first()->timezone)->toBe('Europe/Brussels');
});
