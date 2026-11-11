<?php

use App\Enums\SubscriptionCycle;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Support\HomeDashboard;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
});

it('greets by the hour', function (string $time, string $greeting) {
    expect(HomeDashboard::greeting(Carbon::parse("2026-10-29 {$time}", 'Europe/Brussels')))->toBe($greeting);
})->with([
    ['04:59', 'Good evening'],
    ['05:00', 'Good morning'],
    ['11:59', 'Good morning'],
    ['12:00', 'Good afternoon'],
    ['17:59', 'Good afternoon'],
    ['18:00', 'Good evening'],
    ['23:30', 'Good evening'],
]);

it('reads the hour in the app timezone and leaves the date it was given alone', function () {
    $at = Carbon::parse('2026-10-29 17:30', 'UTC');

    expect(HomeDashboard::greeting($at))->toBe('Good evening')
        ->and($at->timezoneName)->toBe('UTC');
});

it('counts today\'s open tasks in words up to nine', function (int $open, string $line) {
    expect(HomeDashboard::todayLine($open, now()))->toBe($line);
})->with([
    [0, 'all clear today. go enjoy your afternoon'],
    [1, 'one small thing and you’re done'],
    [2, 'two small things and you’re done'],
    [3, 'three small things and you’re done'],
    [9, 'nine small things and you’re done'],
    [10, '10 small things and you’re done'],
]);

it('says all clear for the part of the day the greeting names', function (string $time, string $line) {
    expect(HomeDashboard::todayLine(0, Carbon::parse("2026-10-29 {$time}", 'Europe/Brussels')))->toBe($line);
})->with([
    ['04:59', 'all clear today. go enjoy your evening'],
    ['05:00', 'all clear today. go enjoy your morning'],
    ['11:59', 'all clear today. go enjoy your morning'],
    ['12:00', 'all clear today. go enjoy your afternoon'],
    ['17:59', 'all clear today. go enjoy your afternoon'],
    ['18:00', 'all clear today. go enjoy your evening'],
    ['23:30', 'all clear today. go enjoy your evening'],
]);

it('reads the all-clear hour in the app timezone and leaves the date it was given alone', function () {
    $at = Carbon::parse('2026-10-29 17:30', 'UTC');

    expect(HomeDashboard::todayLine(0, $at))->toBe('all clear today. go enjoy your evening')
        ->and($at->timezoneName)->toBe('UTC');
});

it('counts the same at any hour', function (int $open, string $line) {
    expect(HomeDashboard::todayLine($open, Carbon::parse('2026-10-29 08:00', 'Europe/Brussels')))->toBe($line)
        ->and(HomeDashboard::todayLine($open, Carbon::parse('2026-10-29 20:00', 'Europe/Brussels')))->toBe($line);
})->with([
    [1, 'one small thing and you’re done'],
    [3, 'three small things and you’re done'],
    [10, '10 small things and you’re done'],
]);

it('decides which tasks are on the Today card', function (?int $dueIn, ?int $completedIn, bool $in) {
    $task = Task::factory()->for($this->user)->create([
        'due_date' => $dueIn === null ? null : today()->addDays($dueIn),
        'done' => $completedIn !== null,
        'completed_at' => $completedIn === null ? null : now()->addDays($completedIn),
    ]);

    expect(HomeDashboard::for($this->user)->todayTasks->modelKeys())->toBe($in ? [$task->id] : []);
})->with([
    'due today and open' => [0, null, true],
    'due today and done today' => [0, 0, true],
    'overdue and open' => [-2, null, true],
    'overdue and done today' => [-2, 0, true],
    'due today and done yesterday' => [0, -1, false],
    'overdue and done yesterday' => [-2, -1, false],
    'due tomorrow' => [1, null, false],
    'no date' => [null, null, false],
]);

it('counts a task completed at the very start of today as today\'s', function () {
    $midnight = Task::factory()->for($this->user)->create(['due_date' => today(), 'done' => true, 'completed_at' => today()]);
    Task::factory()->for($this->user)->create(['due_date' => today(), 'done' => true, 'completed_at' => today()->subSecond()]);

    expect(HomeDashboard::for($this->user)->todayTasks->modelKeys())->toBe([$midnight->id]);
});

it('leaves another user\'s tasks off the Today card', function () {
    Task::factory()->create(['due_date' => today()]);

    expect(HomeDashboard::for($this->user)->todayTasks)->toBeEmpty();
});

it('orders the Today card open first, then by id, and counts the open ones', function () {
    $doneFirst = Task::factory()->for($this->user)->create(['due_date' => today(), 'done' => true, 'completed_at' => now()]);
    $dueToday = Task::factory()->for($this->user)->create(['due_date' => today()]);
    $overdue = Task::factory()->for($this->user)->create(['due_date' => today()->subDays(3)]);
    $doneLater = Task::factory()->for($this->user)->create(['due_date' => today()->subDay(), 'done' => true, 'completed_at' => now()]);

    $dashboard = HomeDashboard::for($this->user);

    expect($dashboard->todayTasks->modelKeys())->toBe([$dueToday->id, $overdue->id, $doneFirst->id, $doneLater->id])
        ->and($dashboard->todayLeft)->toBe(2);
});

it('gives the first 5 open items and counts them all', function () {
    GroceryItem::factory()->for($this->user)->create(['done' => true]);
    GroceryItem::factory()->create();
    $items = GroceryItem::factory()->count(6)->for($this->user)->create();

    $dashboard = HomeDashboard::for($this->user);

    expect($dashboard->groceries->modelKeys())->toBe($items->take(5)->modelKeys())
        ->and($dashboard->toBuy)->toBe(6);
});

it('loads each item\'s promo', function () {
    $promo = Promo::factory()->for($this->user)->create();
    GroceryItem::factory()->for($this->user)->create(['promo_id' => $promo->id]);

    expect(HomeDashboard::for($this->user)->groceries->sole()->relationLoaded('promo'))->toBeTrue();
});

it('decides which promos are ending soon', function (int $days, bool $in) {
    $promo = Promo::factory()->for($this->user)->create(['valid_until' => today()->addDays($days)]);

    $dashboard = HomeDashboard::for($this->user);

    expect($dashboard->soonPromos->modelKeys())->toBe($in ? [$promo->id] : [])
        ->and($dashboard->dealsEnding)->toBe($in ? 1 : 0);
})->with([
    'ended yesterday' => [-1, false],
    'ends today' => [0, true],
    'ends in 3 days' => [3, true],
    'ends in 4 days' => [4, false],
]);

it('orders the promos soonest first, then by id', function () {
    $later = Promo::factory()->for($this->user)->create(['valid_until' => today()->addDays(3)]);
    $soonest = Promo::factory()->for($this->user)->create(['valid_until' => today()]);
    $laterToo = Promo::factory()->for($this->user)->create(['valid_until' => today()->addDays(3)]);

    expect(HomeDashboard::for($this->user)->soonPromos->modelKeys())->toBe([$soonest->id, $later->id, $laterToo->id]);
});

it('gives 5 of 6 promos ending and counts them all', function () {
    Promo::factory()->count(6)->for($this->user)->create(['valid_until' => today()->addDay()]);

    $dashboard = HomeDashboard::for($this->user);

    expect($dashboard->soonPromos)->toHaveCount(5)
        ->and($dashboard->dealsEnding)->toBe(6);
});

it('marks a promo linked only with an open item of the user\'s', function (Closure $link, bool $linked) {
    $promo = Promo::factory()->for($this->user)->create(['valid_until' => today()->addDay()]);

    $link($this->user, $promo);

    expect(HomeDashboard::for($this->user)->soonPromos->sole()->linked)->toBe($linked);
})->with([
    'no item' => [fn () => null, false],
    'an open item' => [fn (User $user, Promo $promo) => GroceryItem::factory()->for($user)->create(['promo_id' => $promo->id]), true],
    'a done item' => [fn (User $user, Promo $promo) => GroceryItem::factory()->for($user)->create(['promo_id' => $promo->id, 'done' => true]), false],
    'another user\'s item' => [fn (User $user, Promo $promo) => GroceryItem::factory()->create(['promo_id' => $promo->id]), false],
]);

it('leaves another user\'s promos out', function () {
    Promo::factory()->create(['valid_until' => today()]);

    $dashboard = HomeDashboard::for($this->user);

    expect($dashboard->soonPromos)->toBeEmpty()
        ->and($dashboard->dealsEnding)->toBe(0);
});

it('decides which subscriptions are coming up', function (int $days, bool $in) {
    $subscription = Subscription::factory()->for($this->user)->create(['next_payment_date' => today()->addDays($days)]);

    expect(HomeDashboard::for($this->user)->comingUp->modelKeys())->toBe($in ? [$subscription->id] : []);
})->with([
    'a day ago' => [-1, false],
    'today' => [0, true],
    'in 14 days' => [14, true],
    'in 15 days' => [15, false],
]);

it('leaves a cancelled subscription out and keeps a trial', function () {
    Subscription::factory()->for($this->user)->create(['next_payment_date' => today()->addDay(), 'cancelled_at' => today()->subDay()]);
    $trial = Subscription::factory()->for($this->user)->create(['next_payment_date' => today()->addDay(), 'is_trial' => true]);

    expect(HomeDashboard::for($this->user)->comingUp->modelKeys())->toBe([$trial->id]);
});

it('leaves another user\'s subscriptions out', function () {
    Subscription::factory()->create(['next_payment_date' => today()]);

    expect(HomeDashboard::for($this->user)->comingUp)->toBeEmpty();
});

it('gives 4 of 5 eligible subscriptions in date order', function () {
    $ids = collect([9, 2, 5, 0, 12])->mapWithKeys(fn (int $days) => [
        $days => Subscription::factory()->for($this->user)->create(['next_payment_date' => today()->addDays($days)])->id,
    ]);

    expect(HomeDashboard::for($this->user)->comingUp->modelKeys())->toBe([$ids[0], $ids[2], $ids[5], $ids[9]]);
});

it('breaks a tie between payment dates by id', function () {
    $first = Subscription::factory()->for($this->user)->create(['next_payment_date' => today()->addDay()]);
    $second = Subscription::factory()->for($this->user)->create(['next_payment_date' => today()->addDay()]);

    expect(HomeDashboard::for($this->user)->comingUp->modelKeys())->toBe([$first->id, $second->id]);
});

it('takes the monthly total from the subscription totals', function () {
    Subscription::factory()->for($this->user)->create(['price' => '10.00']);
    Subscription::factory()->for($this->user)->create(['price' => '120.00', 'cycle' => SubscriptionCycle::Yearly]);
    Subscription::factory()->for($this->user)->create(['price' => '5.00', 'is_trial' => true]);
    Subscription::factory()->create(['price' => '99.00']);

    expect(HomeDashboard::for($this->user)->monthly)->toBe(20.0);
});
