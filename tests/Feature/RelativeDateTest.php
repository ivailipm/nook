<?php

use App\Models\Promo;
use App\Support\RelativeDate;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));
});

function promoEnding(string $validUntil): Promo
{
    return new Promo(['valid_until' => $validUntil]);
}

it('formats a date relative to today', function (string $date, string $label) {
    expect(RelativeDate::format(Carbon::parse($date)))->toBe($label);
})->with([
    'today' => ['2026-10-29', 'today'],
    'tomorrow' => ['2026-10-30', 'tomorrow'],
    'yesterday' => ['2026-10-28', 'yesterday'],
    '2 days ahead' => ['2026-10-31', 'Sat'],
    '6 days ahead' => ['2026-11-04', 'Wed'],
    '7 days ahead' => ['2026-11-05', '5 Nov'],
    '2 days ago' => ['2026-10-27', '27 Oct'],
    'another year' => ['2027-01-03', '3 Jan'],
]);

it('counts calendar days whatever the time of day', function (string $time) {
    $this->travelTo(Carbon::parse("2026-10-29 {$time}", 'Europe/Brussels'));

    expect(RelativeDate::format(Carbon::parse('2026-10-30')))->toBe('tomorrow')
        ->and(RelativeDate::format(Carbon::parse('2026-10-28')))->toBe('yesterday')
        ->and(RelativeDate::format(Carbon::parse('2026-11-04')))->toBe('Wed');
})->with(['just after midnight' => '00:00:01', 'late in the evening' => '23:59:59']);

it('writes the weekday and month in English whatever the locale', function () {
    $this->beforeApplicationDestroyed(fn () => Carbon::setLocale(config('app.locale')));

    app()->setLocale('nl');
    Carbon::setLocale('fr');

    expect(RelativeDate::format(Carbon::parse('2026-10-31')))->toBe('Sat')
        ->and(RelativeDate::format(Carbon::parse('2026-11-10')))->toBe('10 Nov');
});

it('labels a promo by the days it has left', function (string $validUntil, int $daysLeft, string $label) {
    $promo = promoEnding($validUntil);

    expect($promo->daysLeft())->toBe($daysLeft)
        ->and($promo->untilLabel())->toBe($label);
})->with([
    'ends today' => ['2026-10-29', 0, 'ends today!'],
    'ends tomorrow' => ['2026-10-30', 1, 'ends tomorrow'],
    'ends in 2 days' => ['2026-10-31', 2, 'until Sat'],
    'ends in 12 days' => ['2026-11-10', 12, 'until 10 Nov'],
    'ended yesterday' => ['2026-10-28', -1, 'expired yesterday'],
    'ended 2 days ago' => ['2026-10-27', -2, 'expired 27 Oct'],
]);

it('counts the days left across a clock change', function (string $today, string $validUntil) {
    $this->travelTo(Carbon::parse("{$today} 12:00", 'Europe/Brussels'));

    expect(promoEnding($validUntil)->daysLeft())->toBe(2);
})->with([
    'October, back to winter time' => ['2026-10-24', '2026-10-26'],
    'March, on to summer time' => ['2026-03-28', '2026-03-30'],
]);
