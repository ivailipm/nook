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

it('counts the days to the next payment', function (string $date, int $days) {
    expect(Subscription::factory()->create(['next_payment_date' => $date])->fresh()->daysUntilNextPayment())->toBe($days);
})->with([
    'in four days' => ['2026-11-02', 4],
    'today' => ['2026-10-29', 0],
    'three days ago' => ['2026-10-26', -3],
]);

it('labels the price by its cycle', function (float $price, SubscriptionCycle $cycle, string $label) {
    expect(Subscription::factory()->create(['price' => $price, 'cycle' => $cycle])->fresh()->priceLabel())->toBe($label);
})->with([
    'monthly' => [24.99, SubscriptionCycle::Monthly, '€24.99/mo'],
    'yearly' => [120.00, SubscriptionCycle::Yearly, '€120.00/yr'],
]);

it('words the receipt and list metas', function (array $attributes, string $receiptMeta, string $listMeta) {
    $subscription = Subscription::factory()->create($attributes)->fresh();

    expect($subscription->receiptMeta())->toBe($receiptMeta)
        ->and($subscription->listMeta())->toBe($listMeta);
})->with([
    'paying, in two days' => [['next_payment_date' => '2026-10-31', 'category' => 'Entertainment'], 'monthly · next Sat 31 Oct', 'Entertainment · monthly · next Sat'],
    'paying, today' => [['next_payment_date' => '2026-10-29', 'category' => 'Entertainment'], 'monthly · next Thu 29 Oct', 'Entertainment · monthly · next today'],
    'paying, tomorrow' => [['next_payment_date' => '2026-10-30', 'category' => 'Entertainment'], 'monthly · next Fri 30 Oct', 'Entertainment · monthly · next tomorrow'],
    'paying, yesterday' => [['next_payment_date' => '2026-10-28', 'category' => 'Entertainment'], 'monthly · was due Wed 28 Oct', 'Entertainment · monthly · was due yesterday'],
    'paying, three days ago' => [['next_payment_date' => '2026-10-26', 'category' => 'Entertainment'], 'monthly · was due Mon 26 Oct', 'Entertainment · monthly · was due 26 Oct'],
    'yearly, in two days' => [['next_payment_date' => '2026-10-31', 'category' => 'Storage', 'cycle' => 'yearly'], 'yearly · next Sat 31 Oct', 'Storage · yearly · next Sat'],
    'yearly, three days ago' => [['next_payment_date' => '2026-10-26', 'category' => 'Storage', 'cycle' => 'yearly'], 'yearly · was due Mon 26 Oct', 'Storage · yearly · was due 26 Oct'],
    'trial, in four days' => [['next_payment_date' => '2026-11-02', 'category' => 'Education', 'is_trial' => true], 'free trial · ends Mon 2 Nov', 'Education · trial until Mon'],
    'trial, today' => [['next_payment_date' => '2026-10-29', 'category' => 'Education', 'is_trial' => true], 'free trial · ends Thu 29 Oct', 'Education · trial until today'],
    'trial, yesterday' => [['next_payment_date' => '2026-10-28', 'category' => 'Education', 'is_trial' => true], 'free trial · ended Wed 28 Oct', 'Education · trial ended yesterday'],
    'trial, three days ago' => [['next_payment_date' => '2026-10-26', 'category' => 'Education', 'is_trial' => true], 'free trial · ended Mon 26 Oct', 'Education · trial ended 26 Oct'],
]);

it('words a cancelled row the same whatever its flags or date', function (array $attributes, string $listMeta) {
    expect(Subscription::factory()->create(['category' => 'Entertainment', ...$attributes])->fresh()->listMeta())->toBe($listMeta);
})->with([
    'paying, date ahead' => [['cancelled_at' => '2026-08-29', 'next_payment_date' => '2026-11-02'], 'cancelled 29 Aug · Entertainment'],
    'paying, date passed' => [['cancelled_at' => '2026-08-29', 'next_payment_date' => '2026-10-26'], 'cancelled 29 Aug · Entertainment'],
    'a trial, date ahead' => [['cancelled_at' => '2026-08-29', 'next_payment_date' => '2026-11-02', 'is_trial' => true], 'cancelled 29 Aug · Entertainment'],
    'a trial, date passed' => [['cancelled_at' => '2026-08-29', 'next_payment_date' => '2026-10-26', 'is_trial' => true], 'cancelled 29 Aug · Entertainment'],
    'yearly' => [['cancelled_at' => '2026-08-29', 'cycle' => 'yearly'], 'cancelled 29 Aug · Entertainment'],
    'cancelled today' => [['cancelled_at' => '2026-10-29'], 'cancelled today · Entertainment'],
    'cancelled yesterday' => [['cancelled_at' => '2026-10-28'], 'cancelled yesterday · Entertainment'],
]);

it('words the trial card by the days to its date', function (string $date, string $headline, string $detail) {
    $subscription = Subscription::factory()->create([
        'name' => 'Duolingo Super',
        'price' => 12.99,
        'next_payment_date' => $date,
        'is_trial' => true,
    ])->fresh();

    expect($subscription->trialHeadline())->toBe($headline)
        ->and($subscription->trialDetail())->toBe($detail);
})->with([
    'in four days' => ['2026-11-02', 'Duolingo Super turns paid on Monday', 'Free until Mon 2 Nov, then €12.99 / month.'],
    'tomorrow' => ['2026-10-30', 'Duolingo Super turns paid on Friday', 'Free until Fri 30 Oct, then €12.99 / month.'],
    'today' => ['2026-10-29', 'Duolingo Super turns paid on Thursday', 'Free until Thu 29 Oct, then €12.99 / month.'],
    'three days ago' => ['2026-10-26', "Duolingo Super's trial ended on Mon 26 Oct", 'Free until Mon 26 Oct. Did it turn paid at €12.99 / month?'],
]);

it('says / year on a yearly trial', function (string $date, string $detail) {
    $subscription = Subscription::factory()->create([
        'price' => 120.00,
        'cycle' => SubscriptionCycle::Yearly,
        'next_payment_date' => $date,
        'is_trial' => true,
    ])->fresh();

    expect($subscription->trialDetail())->toBe($detail);
})->with([
    'in four days' => ['2026-11-02', 'Free until Mon 2 Nov, then €120.00 / year.'],
    'three days ago' => ['2026-10-26', 'Free until Mon 26 Oct. Did it turn paid at €120.00 / year?'],
]);

it('labels the reminder for the day before the date', function (string $date, bool $reminder, ?string $label) {
    $subscription = Subscription::factory()->create(['next_payment_date' => $date, 'is_trial' => true, 'trial_reminder' => $reminder])->fresh();

    expect($subscription->reminderLabel())->toBe($label);
})->with([
    'four days, off' => ['2026-11-02', false, 'Remind me Sunday'],
    'four days, on' => ['2026-11-02', true, 'Reminder set · Sun 1 Nov'],
    'one day, off' => ['2026-10-30', false, 'Remind me Thursday'],
    'one day, on' => ['2026-10-30', true, 'Reminder set · Thu 29 Oct'],
    'today, off' => ['2026-10-29', false, null],
    'today, on' => ['2026-10-29', true, null],
    'three days ago, off' => ['2026-10-26', false, null],
    'three days ago, on' => ['2026-10-26', true, null],
]);

it('leaves the date alone when it words the reminder', function () {
    $subscription = Subscription::factory()->create(['next_payment_date' => '2026-11-02', 'is_trial' => true])->fresh();

    $subscription->reminderLabel();

    expect($subscription->next_payment_date->toDateString())->toBe('2026-11-02');
});

it('tells a live trial from the rest', function (bool $trial, ?string $cancelledAt, bool $liveTrial) {
    expect(Subscription::factory()->create(['is_trial' => $trial, 'cancelled_at' => $cancelledAt])->fresh()->isLiveTrial())->toBe($liveTrial);
})->with([
    'paying' => [false, null, false],
    'on trial' => [true, null, true],
    'cancelled' => [false, '2026-10-28', false],
    'a cancelled trial' => [true, '2026-10-28', false],
]);

it('gives each category its full colour classes', function (SubscriptionCategory $category, string $swatch, string $ring) {
    expect($category->swatchClass())->toBe($swatch)
        ->and($category->ringClass())->toBe($ring);
})->with([
    'Entertainment' => [SubscriptionCategory::Entertainment, 'bg-cat-entertainment', 'inset-ring-cat-entertainment'],
    'Health' => [SubscriptionCategory::Health, 'bg-cat-health', 'inset-ring-cat-health'],
    'Storage' => [SubscriptionCategory::Storage, 'bg-cat-storage', 'inset-ring-cat-storage'],
    'Education' => [SubscriptionCategory::Education, 'bg-cat-education', 'inset-ring-cat-education'],
    'Other' => [SubscriptionCategory::Other, 'bg-cat-other', 'inset-ring-cat-other'],
]);

it('gives the Coming up row its label and its real amount', function (string $price, SubscriptionCycle $cycle, bool $trial, string $label, string $amount) {
    $subscription = Subscription::factory()->create(['price' => $price, 'cycle' => $cycle, 'is_trial' => $trial, 'next_payment_date' => '2026-11-01'])->fresh();

    expect($subscription->comingUpLabel())->toBe($label)
        ->and($subscription->comingUpAmount())->toBe($amount);
})->with([
    'monthly' => ['24.99', SubscriptionCycle::Monthly, false, 'pays Sun 1 Nov', '€24.99'],
    'yearly' => ['120.00', SubscriptionCycle::Yearly, false, 'pays Sun 1 Nov', '€120.00'],
    'trial' => ['12.99', SubscriptionCycle::Monthly, true, 'trial ends Sun 1 Nov', 'then €12.99'],
]);
