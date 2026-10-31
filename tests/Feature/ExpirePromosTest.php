<?php

use App\Actions\ExpirePromos;
use App\Models\GroceryItem;
use App\Models\Promo;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function promoUntil(string $validUntil, array $attributes = []): Promo
{
    return Promo::factory()->create(['valid_until' => $validUntil, ...$attributes]);
}

function linkedItem(Promo $promo): GroceryItem
{
    return GroceryItem::factory()->for($promo->user)->create(['promo_id' => $promo->id]);
}

/**
 * On Thu 29 Oct 2026: promos that ended yesterday, end today, ended 30 days ago with a photo, and ended 29 days ago
 * with a photo, each with a linked item.
 *
 * @return array<string, array{Promo, GroceryItem}>
 */
function expiryFixture(): array
{
    Storage::fake('local');
    Storage::disk('local')->put('promos/30-days.jpg', 'photo');
    Storage::disk('local')->put('promos/29-days.jpg', 'photo');

    $promos = [
        'yesterday' => promoUntil('2026-10-28'),
        'today' => promoUntil('2026-10-29'),
        '30 days ago' => promoUntil('2026-09-29', ['photo_path' => 'promos/30-days.jpg']),
        '29 days ago' => promoUntil('2026-09-30', ['photo_path' => 'promos/29-days.jpg']),
    ];

    return array_map(fn (Promo $promo) => [$promo, linkedItem($promo)], $promos);
}

/**
 * @param  array<string, array{Promo, GroceryItem}>  $fixture
 */
function assertExpired(array $fixture): void
{
    $linked = array_map(fn (array $pair) => $pair[1]->fresh()->promo_id, $fixture);

    expect($linked)->toBe([
        'yesterday' => null,
        'today' => $fixture['today'][0]->id,
        '30 days ago' => null,
        '29 days ago' => null,
    ])->and(Promo::orderBy('id')->pluck('valid_until')->map->toDateString()->all())->toBe(['2026-10-28', '2026-10-29', '2026-09-30']);

    Storage::disk('local')->assertMissing('promos/30-days.jpg');
    Storage::disk('local')->assertExists('promos/29-days.jpg');
}

it('counts a promo as active through its last day and expired from the day after', function (string $time) {
    $this->travelTo("2026-10-29 {$time}");

    $endsToday = promoUntil('2026-10-29');
    $endedYesterday = promoUntil('2026-10-28');
    $startsLater = promoUntil('2026-11-10', ['valid_from' => '2026-11-05']);

    expect(Promo::active()->orderBy('id')->pluck('id')->all())->toBe([$endsToday->id, $startsLater->id])
        ->and(Promo::expired()->pluck('id')->all())->toBe([$endedYesterday->id]);
})->with(['just after midnight' => '00:00:01', 'late in the evening' => '23:59:59']);

it('archives the promos that expired during this Monday-to-Sunday week', function (string $today, array $in, array $out) {
    $this->travelTo("{$today} 12:00");

    foreach ([...$in, ...$out] as $validUntil) {
        promoUntil($validUntil);
    }

    // en_US weeks start on Sunday, so this only passes when the scope sets Monday itself.
    $archived = Carbon::executeWithLocale('en_US', fn () => Promo::expiredThisWeek()->orderBy('valid_until')->pluck('valid_until'));

    expect($archived->map->toDateString()->all())->toBe($in);
})->with([
    'Thu 29 Oct' => ['2026-10-29', ['2026-10-25', '2026-10-28'], ['2026-10-24', '2026-10-29']],
    'Sun 1 Nov' => ['2026-11-01', ['2026-10-25', '2026-10-31'], ['2026-10-24', '2026-11-01']],
    'Mon 2 Nov' => ['2026-11-02', ['2026-11-01'], ['2026-10-31', '2026-11-02']],
]);

it('unlinks items from expired promos and deletes promos 30 days after they ended, with their photos', function () {
    $this->travelTo('2026-10-29 00:00');
    $fixture = expiryFixture();

    expect(app(ExpirePromos::class)->handle())->toBe(['unlinked' => 3, 'deleted' => 1]);

    assertExpired($fixture);
});

it('runs the job with nook:expire-promos', function () {
    $this->travelTo('2026-10-29 00:00');
    $fixture = expiryFixture();

    $this->artisan('nook:expire-promos')
        ->expectsOutputToContain('Unlinked 3 items from expired promos and deleted 1 promo.')
        ->assertSuccessful();

    assertExpired($fixture);
});

it('reports zero counts when there is nothing to expire', function () {
    promoUntil(today()->toDateString());

    $this->artisan('nook:expire-promos')
        ->expectsOutputToContain('Unlinked 0 items from expired promos and deleted 0 promos.')
        ->assertSuccessful();

    expect(Promo::count())->toBe(1);
});

it('schedules nook:expire-promos daily at midnight in the app timezone', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => Str::endsWith((string) $event->command, 'nook:expire-promos'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *')
        ->and($events->first()->timezone)->toBe('Europe/Brussels');
});
