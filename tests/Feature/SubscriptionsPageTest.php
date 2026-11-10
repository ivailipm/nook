<?php

use App\Enums\SubscriptionCategory;
use App\Enums\SubscriptionCycle;
use App\Livewire\Pages\Subscriptions;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\SubscriptionSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->withoutVite();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function subscriptionsHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

function squish(string $text): string
{
    return preg_replace('/\s+/u', ' ', trim($text));
}

/**
 * The receipt's rows as "Name | meta | amount".
 *
 * @return list<string>
 */
function receiptRows(Testable $component): array
{
    return array_map(function (Element $row) {
        $text = $row->firstElementChild;

        return squish($text->firstElementChild->textContent).' | '.squish($text->lastElementChild->textContent).' | '.squish($row->lastElementChild->textContent);
    }, iterator_to_array(subscriptionsHtml($component)->querySelectorAll('.bg-receipt .border-dashed')));
}

/**
 * The list's rows as "Name | meta | price | action label".
 *
 * @return list<string>
 */
function listRows(Testable $component): array
{
    return array_map(function (Element $button) {
        $text = $button->previousElementSibling->previousElementSibling;

        return squish($text->firstElementChild->textContent).' | '.squish($text->lastElementChild->textContent).' | '.squish($button->previousElementSibling->textContent).' | '.squish($button->textContent);
    }, iterator_to_array(subscriptionsHtml($component)->querySelectorAll('button[id^="subscription-action-"]')));
}

function trialCard(Testable $component): ?Element
{
    return subscriptionsHtml($component)->querySelector('section.relative');
}

/**
 * The trial card's button labels.
 *
 * @return list<string>
 */
function trialButtons(Testable $component): array
{
    return array_map(fn (Element $button) => squish($button->textContent), iterator_to_array(trialCard($component)->querySelectorAll('button')));
}

/**
 * The legend's rows as "Name share amount".
 *
 * @return list<string>
 */
function legendRows(Testable $component): array
{
    return array_map(fn (Element $row) => squish($row->textContent), iterator_to_array(subscriptionsHtml($component)->querySelectorAll('section .min-h-7')));
}

function savingsLine(Testable $component): ?string
{
    return subscriptionsHtml($component)->querySelector('p.text-peach')?->textContent;
}

function seededSubscription(string $name): Subscription
{
    return test()->user->subscriptions()->where('name', $name)->sole();
}

dataset('page actions', ['toggleReminder', 'keepTrial', 'cancelTrial', 'cancel', 'resume']);

// The page

it('lays the page out in the prototype\'s two columns and stacks it in the same order', function () {
    app(SubscriptionSeeder::class)->run($this->user);

    $page = subscriptionsHtml(Livewire::test(Subscriptions::class));
    $grid = $page->querySelector('.bg-receipt')->parentElement->parentElement->parentElement;
    $right = $grid->lastElementChild;
    $cards = array_map(fn (Element $card) => squish($card->querySelector('h2')->textContent), iterator_to_array($right->querySelectorAll('section')));

    expect($grid->getAttribute('class'))->toBe('grid grid-cols-1 items-start gap-4.5 wide:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]')
        ->and($grid->firstElementChild->querySelector('.bg-receipt'))->not->toBeNull()
        ->and($right->getAttribute('class'))->toContain('gap-4')
        ->and($cards)->toBe(['Duolingo Super turns paid on Monday', 'By category', 'All subscriptions']);
});

it('changes nothing in the database by being read', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    Subscription::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $before = Subscription::orderBy('id')->get()->map->getAttributes()->all();

    $this->get('/subscriptions')->assertOk();

    expect(Subscription::orderBy('id')->get()->map->getAttributes()->all())->toBe($before);
});

// Receipt

it('prints the seeded receipt', function () {
    app(SubscriptionSeeder::class)->run($this->user);

    $component = Livewire::test(Subscriptions::class);
    $receipt = subscriptionsHtml($component)->querySelector('.bg-receipt');

    expect($receipt->textContent)->toContain('* * * NOOK · OCTOBER * * *', 'THIS MONTH', '€47.96', '≈ €575.52 a year', 'THANK YOU · COME AGAIN')
        ->and(squish($receipt->querySelector('.border-t-2')->textContent))->toBe('TOTAL / MONTH €47.96')
        ->and($receipt->textContent)->not->toContain('Disney+')
        ->and(receiptRows($component))->toBe([
            'Spotify | monthly · next Sat 31 Oct | €6.49',
            'Gym | monthly · next Sun 1 Nov | €24.99',
            'Duolingo Super | free trial · ends Mon 2 Nov | €0.00',
            'Netflix | monthly · next Tue 3 Nov | €13.49',
            'iCloud+ | monthly · next Sat 14 Nov | €2.99',
        ]);
});

it('prints the current month on the receipt', function () {
    $this->travelTo(Carbon::parse('2026-12-03 12:00', 'Europe/Brussels'));

    expect(subscriptionsHtml(Livewire::test(Subscriptions::class))->querySelector('.bg-receipt')->textContent)->toContain('* * * NOOK · DECEMBER * * *');
});

it('colours a trial\'s meta red and every other meta muted', function () {
    app(SubscriptionSeeder::class)->run($this->user);

    $metas = iterator_to_array(subscriptionsHtml(Livewire::test(Subscriptions::class))->querySelectorAll('.bg-receipt .border-dashed'));
    $colourOf = fn (Element $row) => $row->firstElementChild->lastElementChild->getAttribute('class');

    expect($colourOf($metas[2]))->toBe('text-[11px] text-ends-soon')
        ->and($colourOf($metas[0]))->toBe('text-[11px] text-receipt-muted');
});

it('prints a twelfth of a yearly price on the receipt', function () {
    Subscription::factory()->for($this->user)->create(['name' => 'Figma', 'price' => 120.00, 'cycle' => SubscriptionCycle::Yearly, 'next_payment_date' => '2026-10-31']);

    expect(receiptRows(Livewire::test(Subscriptions::class)))->toBe(['Figma | yearly · next Sat 31 Oct | €10.00']);
});

it('tears the receipt with 20 teeth', function () {
    $svg = subscriptionsHtml(Livewire::test(Subscriptions::class))->querySelector('.-rotate-1 > svg');
    $path = $svg->querySelector('path')->getAttribute('d');

    expect($svg->getAttribute('viewBox'))->toBe('0 0 100 4')
        ->and($svg->getAttribute('preserveAspectRatio'))->toBe('none')
        ->and($svg->getAttribute('width'))->toBe('100%')
        ->and($svg->getAttribute('height'))->toBe('10')
        ->and($svg->getAttribute('aria-hidden'))->toBe('true')
        ->and($svg->getAttribute('class'))->toContain('fill-receipt')
        ->and($path)->toStartWith('M0 0 H100 V0 L97.5 4 L95 0')
        ->and($path)->toEndWith('L2.5 4 L0 0 Z')
        ->and(substr_count($path, ' 4 L'))->toBe(20);
});

// Trial card

it('shows the seeded trial card', function () {
    app(SubscriptionSeeder::class)->run($this->user);

    $component = Livewire::test(Subscriptions::class);
    $card = trialCard($component);
    $trial = seededSubscription('Duolingo Super');
    $remind = $card->querySelector('button');

    expect($card->getAttribute('wire:key'))->toBe("trial-{$trial->id}")
        ->and($card->querySelector('span')->textContent)->toBe('TRIAL')
        ->and(squish($card->querySelector('h2')->textContent))->toBe('Duolingo Super turns paid on Monday')
        ->and(squish($card->querySelector('p')->textContent))->toBe('Free until Mon 2 Nov, then €12.99 / month.')
        ->and(trialButtons($component))->toBe(['Remind me Sunday', 'Keep it', 'Cancel trial'])
        ->and($remind->getAttribute('aria-pressed'))->toBe('false')
        ->and($remind->getAttribute('wire:click'))->toBe("toggleReminder({$trial->id})")
        ->and($remind->classList->contains('bg-peach'))->toBeTrue();
});

it('shows the trial card up to 7 days ahead', function (string $date, bool $shown) {
    Subscription::factory()->for($this->user)->create(['is_trial' => true, 'next_payment_date' => $date]);

    expect(trialCard(Livewire::test(Subscriptions::class)) !== null)->toBe($shown);
})->with([
    '+7' => ['2026-11-05', true],
    '+8' => ['2026-11-06', false],
]);

it('shows the lower id when two trials are in the window, even when the other is sooner', function () {
    Subscription::factory()->for($this->user)->create(['name' => 'Later', 'is_trial' => true, 'next_payment_date' => '2026-11-04']);
    Subscription::factory()->for($this->user)->create(['name' => 'Sooner', 'is_trial' => true, 'next_payment_date' => '2026-10-30']);

    expect(squish(trialCard(Livewire::test(Subscriptions::class))->querySelector('h2')->textContent))->toBe('Later turns paid on Wednesday');
});

it('has no remind button on the last day or after the date', function (string $date, string $headline) {
    Subscription::factory()->for($this->user)->create(['name' => 'Duolingo Super', 'is_trial' => true, 'next_payment_date' => $date]);

    $component = Livewire::test(Subscriptions::class);

    expect(squish(trialCard($component)->querySelector('h2')->textContent))->toBe($headline)
        ->and(trialButtons($component))->toBe(['Keep it', 'Cancel trial']);
})->with([
    'today' => ['2026-10-29', 'Duolingo Super turns paid on Thursday'],
    'three days ago' => ['2026-10-26', "Duolingo Super's trial ended on Mon 26 Oct"],
]);

it('shows the reminder as set, pressed and in the soft colours', function () {
    Subscription::factory()->for($this->user)->create(['is_trial' => true, 'trial_reminder' => true, 'next_payment_date' => '2026-11-02']);

    $remind = trialCard(Livewire::test(Subscriptions::class))->querySelector('button');

    expect(squish($remind->textContent))->toBe('Reminder set · Sun 1 Nov')
        ->and($remind->getAttribute('aria-pressed'))->toBe('true')
        ->and($remind->classList->contains('bg-peach/18'))->toBeTrue()
        ->and($remind->classList->contains('text-peach'))->toBeTrue();
});

it('shows no trial card for a cancelled trial', function () {
    Subscription::factory()->for($this->user)->create(['is_trial' => true, 'next_payment_date' => '2026-11-02', 'cancelled_at' => '2026-10-28']);

    expect(trialCard(Livewire::test(Subscriptions::class)))->toBeNull();
});

// By category

it('shows the seeded bar, legend and savings line', function () {
    app(SubscriptionSeeder::class)->run($this->user);

    $component = Livewire::test(Subscriptions::class);
    $bar = subscriptionsHtml($component)->querySelector('[role="img"]');
    $segments = iterator_to_array($bar->querySelectorAll('span'));

    expect($bar->getAttribute('aria-label'))->toBe('Monthly spend by category: Health €24.99, Entertainment €19.98, Storage €2.99')
        ->and(array_map(fn (Element $segment) => $segment->getAttribute('style'), $segments))->toBe(['flex: 2499 1 0', 'flex: 1998 1 0', 'flex: 299 1 0'])
        ->and(array_map(fn (Element $segment) => $segment->getAttribute('title'), $segments))->toBe(['Health: €24.99 a month', 'Entertainment: €19.98 a month', 'Storage: €2.99 a month'])
        ->and($segments[0]->classList->contains('bg-cat-health'))->toBeTrue()
        ->and($segments[1]->classList->contains('bg-cat-entertainment'))->toBeTrue()
        ->and($segments[2]->classList->contains('bg-cat-storage'))->toBeTrue()
        ->and(legendRows($component))->toBe(['Health 52% €24.99', 'Entertainment 42% €19.98', 'Storage 6% €2.99'])
        ->and(savingsLine($component))->toBe('saved €15.98 since cancelling Disney+');
});

it('still shows a segment for a free paying subscription', function () {
    Subscription::factory()->for($this->user)->create(['price' => 0, 'category' => SubscriptionCategory::Other]);

    $component = Livewire::test(Subscriptions::class);

    expect(subscriptionsHtml($component)->querySelector('[role="img"] span')->getAttribute('style'))->toBe('flex: 1 1 0')
        ->and(legendRows($component))->toBe(['Other 0% €0.00']);
});

it('says nothing is paying but still shows the savings', function () {
    Subscription::factory()->for($this->user)->create(['name' => 'Disney+', 'price' => 7.99, 'cancelled_at' => '2026-10-28']);

    $component = Livewire::test(Subscriptions::class);
    $page = subscriptionsHtml($component);

    expect($page->querySelector('[role="img"]'))->toBeNull()
        ->and(legendRows($component))->toBe([])
        ->and($page->documentElement->textContent)->toContain('nothing paying yet')
        ->and(savingsLine($component))->toBe('saved €7.99 since cancelling Disney+');
});

// List

it('lists the seeded subscriptions, live then cancelled', function () {
    app(SubscriptionSeeder::class)->run($this->user);

    expect(listRows(Livewire::test(Subscriptions::class)))->toBe([
        'Spotify | Entertainment · monthly · next Sat | €6.49/mo | Cancel',
        'Gym | Health · monthly · next Sun | €24.99/mo | Cancel',
        'Duolingo Super | Education · trial until Mon | €12.99/mo | Cancel',
        'Netflix | Entertainment · monthly · next Tue | €13.49/mo | Cancel',
        'iCloud+ | Storage · monthly · next 14 Nov | €2.99/mo | Cancel',
        'Disney+ | cancelled 29 Aug · Entertainment | €7.99/mo | Resume',
    ]);
});

it('labels the row buttons, keys the rows and only sends ids', function () {
    app(SubscriptionSeeder::class)->run($this->user);

    $page = subscriptionsHtml(Livewire::test(Subscriptions::class));
    $netflix = seededSubscription('Netflix');
    $disney = seededSubscription('Disney+');
    $cancel = $page->getElementById("subscription-action-{$netflix->id}");
    $resume = $page->getElementById("subscription-action-{$disney->id}");

    expect($cancel->getAttribute('aria-label'))->toBe('Cancel Netflix')
        ->and($cancel->getAttribute('x-on:click'))->toContain("\$wire.cancel({$netflix->id})")
        ->and($cancel->getAttribute('x-on:click'))->not->toContain('Netflix')
        ->and($cancel->parentElement->getAttribute('wire:key'))->toBe("subscription-{$netflix->id}")
        ->and($resume->getAttribute('aria-label'))->toBe('Resume Disney+')
        ->and($resume->getAttribute('x-on:click'))->toContain("\$wire.resume({$disney->id})")
        ->and($resume->getAttribute('x-on:click'))->toContain("document.getElementById('subscription-action-{$disney->id}')?.focus()");
});

it('fades a cancelled row\'s avatar, name and amount, but not its meta and button', function () {
    Subscription::factory()->for($this->user)->create(['cancelled_at' => '2026-10-28']);

    $button = subscriptionsHtml(Livewire::test(Subscriptions::class))->querySelector('button[id^="subscription-action-"]');
    $amount = $button->previousElementSibling;
    $text = $amount->previousElementSibling;
    $avatar = $text->previousElementSibling;

    expect($avatar->classList->contains('opacity-60'))->toBeTrue()
        ->and($text->firstElementChild->classList->contains('opacity-60'))->toBeTrue()
        ->and($text->firstElementChild->classList->contains('line-through'))->toBeTrue()
        ->and($amount->classList->contains('opacity-60'))->toBeTrue()
        ->and($text->lastElementChild->classList->contains('opacity-60'))->toBeFalse()
        ->and($button->classList->contains('opacity-60'))->toBeFalse();
});

it('does not fade a live row', function () {
    Subscription::factory()->for($this->user)->create();

    expect(subscriptionsHtml(Livewire::test(Subscriptions::class))->querySelector('.opacity-60'))->toBeNull();
});

it('shows a yearly price with /yr', function () {
    Subscription::factory()->for($this->user)->create(['name' => 'Figma', 'price' => 120.00, 'cycle' => SubscriptionCycle::Yearly, 'next_payment_date' => '2026-10-31', 'category' => SubscriptionCategory::Other]);

    expect(listRows(Livewire::test(Subscriptions::class)))->toBe(['Figma | Other · yearly · next Sat | €120.00/yr | Cancel']);
});

it('takes the avatar letter from the name, multibyte safe and as typed, ringed in its category colour', function (string $name, SubscriptionCategory $category, string $letter, string $ring) {
    Subscription::factory()->for($this->user)->create(['name' => $name, 'category' => $category]);

    $avatar = subscriptionsHtml(Livewire::test(Subscriptions::class))->querySelector('button[id^="subscription-action-"]')->parentElement->firstElementChild;

    expect($avatar->textContent)->toBe($letter)
        ->and($avatar->getAttribute('aria-hidden'))->toBe('true')
        ->and($avatar->classList->contains($ring))->toBeTrue()
        ->and($avatar->classList->contains('inset-ring-[1.5px]'))->toBeTrue();
})->with([
    'an accent' => ['Écoute', SubscriptionCategory::Entertainment, 'É', 'inset-ring-cat-entertainment'],
    'a lower-case letter' => ['iCloud+', SubscriptionCategory::Storage, 'i', 'inset-ring-cat-storage'],
    'another category' => ['Gym', SubscriptionCategory::Health, 'G', 'inset-ring-cat-health'],
]);

it('opens the add sheet from the + Add button', function () {
    $add = subscriptionsHtml(Livewire::test(Subscriptions::class))->querySelector('button[aria-label="Add a subscription"]');

    expect(squish($add->textContent))->toBe('+ Add')
        ->and($add->getAttribute('type'))->toBe('button')
        ->and($add->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($add->getAttribute('x-on:click'))->toBe("\$dispatch('open-sheet', { name: 'add-subscription' })");
});

it('opens the edit sheet from a live and a cancelled row, with the whole row as the hit area', function (string $name) {
    app(SubscriptionSeeder::class)->run($this->user);
    $subscription = seededSubscription($name);

    $page = subscriptionsHtml(Livewire::test(Subscriptions::class));
    $open = $page->querySelector("button#subscription-open-{$subscription->id}");
    $row = $open->parentElement;

    expect($open->getAttribute('type'))->toBe('button')
        ->and($open->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($open->getAttribute('x-on:click'))->toBe("\$dispatch('show-subscription', { id: {$subscription->id} })")
        ->and($open->hasAttribute('aria-pressed'))->toBeFalse()
        ->and($open->hasAttribute('aria-label'))->toBeFalse()
        ->and(squish($open->firstElementChild->textContent))->toBe($name)
        ->and($open->previousElementSibling->getAttribute('aria-hidden'))->toBe('true')
        ->and($open->previousElementSibling->textContent)->toBe(mb_substr($name, 0, 1))
        ->and($open->nextElementSibling->textContent)->toBe($subscription->priceLabel())
        ->and($open->nextElementSibling->nextElementSibling->getAttribute('id'))->toBe("subscription-action-{$subscription->id}")
        ->and($open->querySelector('div'))->toBeNull()
        ->and($open->getAttribute('class'))->toBe('flex min-w-0 flex-1 cursor-pointer flex-col text-left break-words before:absolute before:inset-0')
        ->and($row->getAttribute('wire:key'))->toBe("subscription-{$subscription->id}")
        ->and($row->classList->contains('relative'))->toBeTrue();
})->with(['live' => 'Netflix', 'cancelled' => 'Disney+']);

// Actions

it('flips the reminder, with a toast only when turning it on', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $trial = seededSubscription('Duolingo Super');

    Livewire::test(Subscriptions::class)
        ->call('toggleReminder', $trial->id)
        ->assertDispatched('toast', message: 'We will nudge you the day before');

    expect($trial->fresh()->trial_reminder)->toBeTrue();

    Livewire::test(Subscriptions::class)
        ->call('toggleReminder', $trial->id)
        ->assertNotDispatched('toast');

    expect($trial->fresh()->trial_reminder)->toBeFalse()
        ->and($trial->fresh()->next_payment_date->toDateString())->toBe('2026-11-02');
});

it('flips the reminder on the day before the date', function () {
    $trial = Subscription::factory()->for($this->user)->create(['is_trial' => true, 'next_payment_date' => '2026-10-30']);

    Livewire::test(Subscriptions::class)
        ->call('toggleReminder', $trial->id)
        ->assertDispatched('toast', message: 'We will nudge you the day before');

    expect($trial->fresh()->trial_reminder)->toBeTrue();
});

it('keeps a trial as a paid subscription', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $trial = seededSubscription('Duolingo Super');

    $component = Livewire::test(Subscriptions::class)
        ->call('keepTrial', $trial->id)
        ->assertDispatched('toast', message: 'Duolingo Super is now a paid subscription');

    expect($trial->fresh()->is_trial)->toBeFalse()
        ->and($trial->fresh()->cancelled_at)->toBeNull()
        ->and($trial->fresh()->next_payment_date->toDateString())->toBe('2026-11-02')
        ->and(trialCard($component))->toBeNull()
        ->and(legendRows($component))->toContain('Education 21% €12.99')
        ->and(subscriptionsHtml($component)->querySelector('.bg-receipt')->textContent)->toContain('€60.95');
});

it('cancels a trial, keeps it a trial and leaves it out of the savings', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $trial = seededSubscription('Duolingo Super');

    $component = Livewire::test(Subscriptions::class)
        ->call('cancelTrial', $trial->id)
        ->assertDispatched('toast', message: 'Trial cancelled. Nothing will be charged');

    expect($trial->fresh()->is_trial)->toBeTrue()
        ->and($trial->fresh()->cancelled_at->toDateString())->toBe('2026-10-29')
        ->and($trial->fresh()->next_payment_date->toDateString())->toBe('2026-11-02')
        ->and(trialCard($component))->toBeNull()
        ->and(last(listRows($component)))->toBe('Duolingo Super | trial cancelled today · Education | €12.99/mo | Resume')
        ->and(subscriptionsHtml($component)->querySelector('.bg-receipt')->textContent)->toContain('€47.96')
        ->and(savingsLine($component))->toBe('saved €15.98 since cancelling Disney+');
});

it('words a trial cancelled from its list row the same and leaves it out of the savings', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $trial = seededSubscription('Duolingo Super');

    $component = Livewire::test(Subscriptions::class)
        ->call('cancel', $trial->id)
        ->assertDispatched('toast', message: 'Duolingo Super marked as cancelled');

    expect(last(listRows($component)))->toBe('Duolingo Super | trial cancelled today · Education | €12.99/mo | Resume')
        ->and(savingsLine($component))->toBe('saved €15.98 since cancelling Disney+');
});

it('says only that a trial is cancelled once its date has passed', function () {
    $trial = Subscription::factory()->for($this->user)->create(['is_trial' => true, 'next_payment_date' => '2026-10-26']);

    Livewire::test(Subscriptions::class)
        ->call('cancelTrial', $trial->id)
        ->assertDispatched('toast', message: 'Trial cancelled');

    expect($trial->fresh()->is_trial)->toBeTrue()
        ->and($trial->fresh()->cancelled_at->toDateString())->toBe('2026-10-29');
});

it('cancels a row, moves it to the bottom and takes it off the receipt', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $netflix = seededSubscription('Netflix');

    $component = Livewire::test(Subscriptions::class)
        ->call('cancel', $netflix->id)
        ->assertDispatched('toast', message: 'Netflix marked as cancelled');
    $fresh = $netflix->fresh();

    expect($fresh->cancelled_at->toDateString())->toBe('2026-10-29')
        ->and($fresh->is_trial)->toBeFalse()
        ->and($fresh->next_payment_date->toDateString())->toBe('2026-11-03')
        ->and(last(listRows($component)))->toBe('Netflix | cancelled today · Entertainment | €13.49/mo | Resume')
        ->and(subscriptionsHtml($component)->querySelector('.bg-receipt')->textContent)->toContain('€34.47', '≈ €413.64 a year')
        ->and(savingsLine($component))->toBe('saved €29.47 since cancelling Netflix, Disney+');
});

it('resumes a row and leaves the rest alone', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $disney = seededSubscription('Disney+');

    $component = Livewire::test(Subscriptions::class)
        ->call('resume', $disney->id)
        ->assertDispatched('toast', message: 'Disney+ is back on');

    expect($disney->fresh()->cancelled_at)->toBeNull()
        ->and($disney->fresh()->is_trial)->toBeFalse()
        ->and($disney->fresh()->next_payment_date->toDateString())->toBe('2026-10-29')
        ->and(savingsLine($component))->toBeNull();
});

it('keeps the date of a row cancelled 61 days ago when cancel comes again', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $disney = seededSubscription('Disney+');

    Livewire::test(Subscriptions::class)
        ->call('cancel', $disney->id)
        ->assertNotDispatched('toast');

    expect($disney->fresh()->cancelled_at->toDateString())->toBe('2026-08-29');
});

it('does nothing when the action no longer applies', function (string $action, array $attributes) {
    $subscription = Subscription::factory()->for($this->user)->create($attributes);
    Subscription::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $before = $subscription->fresh()->getAttributes();

    Livewire::test(Subscriptions::class)
        ->call($action, $subscription->id)
        ->assertNotDispatched('toast');

    expect($subscription->fresh()->getAttributes())->toBe($before);
})->with([
    'resume on a live row' => ['resume', ['next_payment_date' => '2026-11-02']],
    'keepTrial on a paying row' => ['keepTrial', ['next_payment_date' => '2026-11-02']],
    'cancelTrial on a paying row' => ['cancelTrial', ['next_payment_date' => '2026-11-02']],
    'keepTrial on a cancelled trial' => ['keepTrial', ['is_trial' => true, 'next_payment_date' => '2026-11-02', 'cancelled_at' => '2026-10-28']],
    'cancelTrial on a cancelled trial' => ['cancelTrial', ['is_trial' => true, 'next_payment_date' => '2026-11-02', 'cancelled_at' => '2026-10-28']],
    'toggleReminder on the last day' => ['toggleReminder', ['is_trial' => true, 'next_payment_date' => '2026-10-29']],
    'toggleReminder three days after' => ['toggleReminder', ['is_trial' => true, 'next_payment_date' => '2026-10-26']],
    'toggleReminder on a cancelled trial' => ['toggleReminder', ['is_trial' => true, 'next_payment_date' => '2026-11-02', 'cancelled_at' => '2026-10-28']],
    'toggleReminder on a paying row' => ['toggleReminder', ['next_payment_date' => '2026-11-02']],
    'cancel on a cancelled row' => ['cancel', ['next_payment_date' => '2026-11-02', 'cancelled_at' => '2026-08-29']],
]);

it('brings a trial cancelled from the list back as a trial', function () {
    $trial = Subscription::factory()->for($this->user)->create(['is_trial' => true, 'next_payment_date' => '2026-11-02']);

    Livewire::test(Subscriptions::class)->call('cancel', $trial->id);

    expect($trial->fresh()->is_trial)->toBeTrue()
        ->and($trial->fresh()->isCancelled())->toBeTrue();

    Livewire::test(Subscriptions::class)->call('resume', $trial->id);

    expect($trial->fresh()->is_trial)->toBeTrue()
        ->and($trial->fresh()->isCancelled())->toBeFalse();
});

it('brings a trial cancelled from its card back as a live trial', function () {
    $trial = Subscription::factory()->for($this->user)->create(['is_trial' => true, 'next_payment_date' => '2026-11-02']);

    Livewire::test(Subscriptions::class)->call('cancelTrial', $trial->id);
    Livewire::test(Subscriptions::class)->call('resume', $trial->id);

    expect($trial->fresh()->isLiveTrial())->toBeTrue()
        ->and($trial->fresh()->isPaying())->toBeFalse()
        ->and($trial->fresh()->next_payment_date->toDateString())->toBe('2026-11-02');
});

it('shows a resumed trial on its card, its row and the receipt, with the total unchanged', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $trial = seededSubscription('Duolingo Super');

    $component = Livewire::test(Subscriptions::class)
        ->call('cancelTrial', $trial->id)
        ->call('resume', $trial->id)
        ->assertDispatched('toast', message: 'Duolingo Super is back on');

    expect(squish(trialCard($component)->querySelector('h2')->textContent))->toBe('Duolingo Super turns paid on Monday')
        ->and(listRows($component))->toContain('Duolingo Super | Education · trial until Mon | €12.99/mo | Cancel')
        ->and(receiptRows($component))->toContain('Duolingo Super | free trial · ends Mon 2 Nov | €0.00')
        ->and(squish(subscriptionsHtml($component)->querySelector('.bg-receipt .border-t-2')->textContent))->toBe('TOTAL / MONTH €47.96')
        ->and(savingsLine($component))->toBe('saved €15.98 since cancelling Disney+');
});

it('refreshes on subscription-changed and gives the saved row its focus back', function () {
    app(SubscriptionSeeder::class)->run($this->user);
    $netflix = seededSubscription('Netflix');
    $component = Livewire::test(Subscriptions::class);

    $netflix->update(['price' => '15.99', 'cycle' => SubscriptionCycle::Yearly]);

    expect(listRows($component))->toContain('Netflix | Entertainment · monthly · next Tue | €13.49/mo | Cancel');

    $component->dispatch('subscription-changed', id: $netflix->id)
        ->assertJs("document.getElementById('subscription-open-{$netflix->id}')?.focus()");
    $receipt = subscriptionsHtml($component)->querySelector('.bg-receipt');

    expect(listRows($component))->toContain('Netflix | Entertainment · yearly · next Tue | €15.99/yr | Cancel')
        ->and(receiptRows($component))->toContain('Netflix | yearly · next Tue 3 Nov | €1.33')
        ->and(squish($receipt->querySelector('.border-t-2')->textContent))->toBe('TOTAL / MONTH €35.80')
        ->and($receipt->textContent)->toContain('≈ €429.63 a year');
});

// Another user's id

it('refuses every action on another user\'s subscription', function (string $action) {
    $theirs = Subscription::factory()->create([
        'is_trial' => true,
        'next_payment_date' => '2026-11-02',
        'cancelled_at' => $action === 'resume' ? '2026-10-28' : null,
    ]);
    $before = $theirs->fresh()->getAttributes();

    Livewire::test(Subscriptions::class)
        ->call($action, $theirs->id)
        ->assertNotFound();

    expect($theirs->fresh()->getAttributes())->toBe($before);
})->with('page actions');

it('never shows another user\'s subscriptions', function () {
    Subscription::factory()->create(['name' => 'Theirs', 'price' => 99.00, 'is_trial' => true, 'next_payment_date' => '2026-11-02']);
    Subscription::factory()->create(['name' => 'Theirs too', 'price' => 99.00, 'cancelled_at' => '2026-10-28']);
    Subscription::factory()->for($this->user)->create(['name' => 'Mine', 'price' => 5.00, 'next_payment_date' => '2026-11-02', 'category' => SubscriptionCategory::Other]);

    $this->get('/subscriptions')->assertOk()->assertSee('Mine')->assertDontSee('Theirs')->assertDontSee('€99.00');
});

// Escaping

it('escapes a name everywhere it shows', function () {
    $raw = '<script>alert(1)</script>';
    $escaped = '&lt;script&gt;alert(1)&lt;/script&gt;';

    Subscription::factory()->for($this->user)->create(['name' => $raw, 'is_trial' => true, 'next_payment_date' => '2026-11-02']);
    Subscription::factory()->for($this->user)->create(['name' => $raw, 'price' => 7.99, 'cancelled_at' => '2026-10-28']);

    $html = $this->get('/subscriptions')->assertOk()->getContent();

    expect($html)->not->toContain($raw)
        ->and($html)->toContain("saved €7.99 since cancelling {$escaped}")
        ->and($html)->toContain("{$escaped} turns paid on Monday")
        ->and($html)->toContain("aria-label=\"Cancel {$escaped}\"")
        ->and($html)->toContain("aria-label=\"Resume {$escaped}\"");
});

// Past dates

it('words past dates honestly and changes nothing for them', function () {
    Subscription::factory()->for($this->user)->create(['name' => 'Netflix', 'price' => 13.49, 'next_payment_date' => '2026-10-26', 'category' => SubscriptionCategory::Entertainment]);
    Subscription::factory()->for($this->user)->create(['name' => 'Duolingo Super', 'price' => 12.99, 'next_payment_date' => '2026-10-26', 'category' => SubscriptionCategory::Education, 'is_trial' => true]);
    Subscription::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $before = Subscription::orderBy('id')->get()->map(fn (Subscription $subscription) => $subscription->only('next_payment_date', 'is_trial', 'updated_at'))->all();

    $html = $this->get('/subscriptions')->assertOk()->getContent();

    expect($html)->not->toContain('next Mon 26 Oct')
        ->and($html)->not->toContain('turns paid')
        ->and(Subscription::orderBy('id')->get()->map(fn (Subscription $subscription) => $subscription->only('next_payment_date', 'is_trial', 'updated_at'))->all())->toEqual($before);

    $component = Livewire::test(Subscriptions::class);
    $card = trialCard($component);

    expect(receiptRows($component))->toBe([
        'Netflix | monthly · was due Mon 26 Oct | €13.49',
        'Duolingo Super | free trial · ended Mon 26 Oct | €0.00',
    ])
        ->and(listRows($component))->toBe([
            'Netflix | Entertainment · monthly · was due 26 Oct | €13.49/mo | Cancel',
            'Duolingo Super | Education · trial ended 26 Oct | €12.99/mo | Cancel',
        ])
        ->and(squish($card->querySelector('h2')->textContent))->toBe("Duolingo Super's trial ended on Mon 26 Oct")
        ->and(squish($card->querySelector('p')->textContent))->toBe('Free until Mon 26 Oct. Did it turn paid at €12.99 / month?');
});

it('gives the same totals for past dates as for future ones', function () {
    $netflix = Subscription::factory()->for($this->user)->create(['price' => 13.49, 'next_payment_date' => '2026-10-26', 'category' => SubscriptionCategory::Entertainment]);
    $trial = Subscription::factory()->for($this->user)->create(['price' => 12.99, 'next_payment_date' => '2026-10-26', 'is_trial' => true]);
    $totalsOf = function () {
        $component = Livewire::test(Subscriptions::class);

        return [squish(subscriptionsHtml($component)->querySelector('.bg-receipt .border-t-2')->textContent), legendRows($component), savingsLine($component)];
    };

    $past = $totalsOf();

    $netflix->update(['next_payment_date' => '2026-11-02']);
    $trial->update(['next_payment_date' => '2026-11-02']);

    expect($past)->toBe(['TOTAL / MONTH €13.49', ['Entertainment 100% €13.49'], null])
        ->and($totalsOf())->toBe($past);
});

// Empty

it('shows an honest page with no subscriptions', function () {
    $html = HTMLDocument::createFromString($this->get('/subscriptions')->assertOk()->getContent(), LIBXML_NOERROR);
    $receipt = $html->querySelector('.bg-receipt');
    $main = $html->querySelector('main');

    expect(squish($receipt->querySelector('.font-display')->textContent))->toBe('€0.00')
        ->and(squish($receipt->querySelector('.border-t-2')->textContent))->toBe('TOTAL / MONTH €0.00')
        ->and($receipt->textContent)->toContain('≈ €0.00 a year')
        ->and($receipt->querySelectorAll('.border-dashed'))->toHaveCount(0)
        ->and($main->textContent)->toContain('nothing paying yet', 'nothing here yet. add your first one')
        ->and($main->querySelector('[role="img"]'))->toBeNull()
        ->and($main->querySelector('section.relative'))->toBeNull()
        ->and($main->querySelector('button[aria-label="Add a subscription"]'))->not->toBeNull();
});
