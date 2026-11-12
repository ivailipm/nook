<?php

use App\Enums\SubscriptionCycle;
use App\Livewire\Pages\Home;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->withoutVite();
});

/**
 * Runs all four seeders and signs in as the seeded user.
 */
function homeSeededUser(): User
{
    test()->seed();

    $user = User::sole();

    test()->actingAs($user);

    return $user;
}

function homeSignedInUser(): User
{
    $user = User::factory()->create();

    test()->actingAs($user);

    return $user;
}

function homeHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

function homeText(?Element $element): string
{
    return preg_replace('/\s+/u', ' ', trim($element->textContent));
}

function homeCard(Testable $component, string $title): ?Element
{
    foreach (homeHtml($component)->querySelectorAll('section') as $card) {
        if (trim($card->querySelector('h2')->textContent) === $title) {
            return $card;
        }
    }

    return null;
}

function homeTodayLine(Testable $component): string
{
    return homeText(homeHtml($component)->querySelector('h1')->parentElement->lastElementChild);
}

function homeAside(Testable $component, string $title): string
{
    return homeText(homeCard($component, $title)->querySelector('h2')->nextElementSibling);
}

/**
 * The chips as "text | href".
 *
 * @return list<string>
 */
function homeChips(Testable $component): array
{
    $chips = homeHtml($component)->querySelector('h1')->parentElement->nextElementSibling->querySelectorAll('a');

    return array_map(fn (Element $chip) => homeText($chip).' | '.$chip->getAttribute('href'), iterator_to_array($chips));
}

/**
 * The Today card's rows as "Title: meta", with " (done)" after a pressed one.
 *
 * @return list<string>
 */
function homeTodayRows(Testable $component): array
{
    return array_map(function (Element $checkbox) {
        $text = $checkbox->nextElementSibling;
        $row = homeText($text->firstElementChild).': '.homeText($text->lastElementChild);

        return $checkbox->getAttribute('aria-pressed') === 'true' ? "{$row} (done)" : $row;
    }, iterator_to_array(homeCard($component, 'Today')->querySelectorAll('button[aria-pressed]')));
}

/**
 * The Groceries card's rows as "Name: badge or store".
 *
 * @return list<string>
 */
function homeGroceryRows(Testable $component): array
{
    return array_map(function (Element $checkbox) {
        $name = $checkbox->nextElementSibling;

        return homeText($name).': '.homeText($name->nextElementSibling);
    }, iterator_to_array(homeCard($component, 'Groceries')->querySelectorAll('button[aria-pressed]')));
}

/**
 * The polaroids' list items.
 *
 * @return list<Element>
 */
function homeDealItems(Testable $component): array
{
    return iterator_to_array(homeCard($component, 'Deals ending soon')->querySelectorAll('ul > li'));
}

/**
 * The Coming up card's rows as "Name | label | amount".
 *
 * @return list<string>
 */
function homeComingUpRows(Testable $component): array
{
    return array_map(function (Element $row) {
        $text = $row->firstElementChild->nextElementSibling;

        return homeText($text->firstElementChild).' | '.homeText($text->lastElementChild).' | '.homeText($row->lastElementChild);
    }, iterator_to_array(homeCard($component, 'Coming up')->querySelectorAll('.border-t')));
}

/**
 * The chips' text, without their links.
 *
 * @return list<string>
 */
function homeChipTexts(Testable $component): array
{
    return array_map(fn (string $chip) => explode(' | ', $chip)[0], homeChips($component));
}

function homeBell(Testable $component): Element
{
    return homeHtml($component)->querySelector('button[aria-label="Morning nudge"]');
}

it('renders the seeded page in the app shell', function () {
    homeSeededUser();

    $page = HTMLDocument::createFromString($this->get('/')->assertOk()->assertSeeLivewire(Home::class)->getContent(), LIBXML_NOERROR);

    expect($page->querySelector('h1')->innerHTML)->toBe('Good afternoon,<br><em>Ivayla</em>')
        ->and(homeText($page->querySelector('h1')->previousElementSibling))->toBe('THURSDAY · 29 OCTOBER');
});

it('shows the seeded greeting, line and chips', function () {
    homeSeededUser();

    $component = Livewire::test(Home::class);

    expect(homeHtml($component)->querySelector('h1')->innerHTML)->toBe('Good afternoon,<br><em>Ivayla</em>')
        ->and(homeText(homeHtml($component)->querySelector('h1')->previousElementSibling))->toBe('THURSDAY · 29 OCTOBER')
        ->and(homeTodayLine($component))->toBe('three small things and you’re done')
        ->and(homeChips($component))->toBe([
            '6 to buy | '.route('groceries'),
            '3 deals ending | '.route('promos'),
            '€47.96 / mo | '.route('subscriptions'),
        ]);
});

it('shows the seeded Today card', function () {
    homeSeededUser();

    $component = Livewire::test(Home::class);
    $card = homeCard($component, 'Today');

    expect(homeTodayRows($component))->toBe([
        'Return library book: School · overdue',
        'Send CV to two companies: Internship · due today',
        'Put out the PMD bag: Home · due today',
        'Water the plants: Home · done (done)',
    ])
        ->and(homeAside($component, 'Today'))->toBe('3 left')
        ->and(homeText($card->querySelector('a')))->toBe('All tasks →')
        ->and($card->querySelector('a')->getAttribute('href'))->toBe(route('todo'))
        ->and($card->querySelector('button[aria-pressed="true"]')->getAttribute('aria-label'))->toBe('Mark not done: Water the plants')
        ->and($card->querySelector('button[aria-pressed="false"]')->getAttribute('aria-label'))->toBe('Complete: Return library book');
});

it('shows the seeded Groceries card, without the sixth item', function () {
    homeSeededUser();

    $component = Livewire::test(Home::class);
    $card = homeCard($component, 'Groceries');

    expect(homeGroceryRows($component))->toBe([
        'Coffee beans: 2+1 free · Colruyt',
        'Dish soap: −30% · Delhaize',
        'Oat milk: Aldi',
        'Spinach: Lidl',
        'Greek yoghurt: 2nd ½ price · Lidl',
    ])
        ->and($card->textContent)->not->toContain('Laundry pods')
        ->and(homeText($card->querySelector('a')))->toBe('Promo board →')
        ->and($card->querySelector('a')->getAttribute('href'))->toBe(route('promos'))
        ->and($card->querySelector('button[aria-pressed]')->getAttribute('aria-label'))->toBe('Tick off Coffee beans');
});

it('shows the seeded deals, soonest first', function () {
    homeSeededUser();

    $component = Livewire::test(Home::class);

    expect(array_map(fn (Element $item) => homeText($item), homeDealItems($component)))->toBe([
        'Delhaize Dish soap −30% ends tomorrow on list',
        'Colruyt Coffee beans 1 kg 2+1 free until Sat on list',
        'Lidl Avocados ×4 €2.49 until Sat',
    ])
        ->and(homeText(homeCard($component, 'Deals ending soon')))->toContain("don't miss these!");
});

it('shows the seeded Coming up card', function () {
    homeSeededUser();

    $component = Livewire::test(Home::class);
    $card = homeCard($component, 'Coming up');

    expect(homeComingUpRows($component))->toBe([
        'Spotify | pays Sat 31 Oct | €6.49',
        'Gym | pays Sun 1 Nov | €24.99',
        'Duolingo Super | trial ends Mon 2 Nov | then €12.99',
        'Netflix | pays Tue 3 Nov | €13.49',
    ])
        ->and(homeText($card->querySelector('a')))->toBe('All subscriptions →')
        ->and($card->querySelector('a')->getAttribute('href'))->toBe(route('subscriptions'));
});

it('colours only a trial\'s Coming up label', function () {
    homeSeededUser();

    $labels = array_map(
        fn (Element $row) => $row->firstElementChild->nextElementSibling->lastElementChild,
        iterator_to_array(homeCard(Livewire::test(Home::class), 'Coming up')->querySelectorAll('.border-t')),
    );

    expect(array_map(fn (Element $label) => $label->classList->contains('text-accent'), $labels))->toBe([false, false, true, false])
        ->and(array_map(fn (Element $label) => $label->classList->contains('text-cream-muted'), $labels))->toBe([true, true, false, true]);
});

it('keeps the wordmark and log-out for the phone and the bell at every width', function () {
    homeSeededUser();

    $page = homeHtml(Livewire::test(Home::class));
    $bell = $page->querySelector('button[aria-label="Morning nudge"]');
    $logOut = $page->querySelector('button[aria-label="Log out"]');

    expect($logOut->closest('form')->getAttribute('action'))->toBe(route('logout'))
        ->and($logOut->closest('form')->classList->contains('wide:hidden'))->toBeTrue()
        ->and($bell->closest('.wide\:hidden'))->toBeNull()
        ->and($bell->classList->contains('size-11'))->toBeTrue()
        ->and(homeText($bell->parentElement->previousElementSibling))->toBe('nook')
        ->and($bell->parentElement->previousElementSibling->classList->contains('wide:hidden'))->toBeTrue();
});

it('says the greeting in the app timezone', function () {
    homeSignedInUser();

    $this->travelTo(Carbon::parse('2026-10-29 17:30', 'UTC'));

    expect(homeHtml(Livewire::test(Home::class))->querySelector('h1')->textContent)->toStartWith('Good evening,');
});

it('ends the all-clear on the part of the day the greeting names', function (string $time, string $greeting, string $line) {
    homeSignedInUser();

    $this->travelTo(Carbon::parse("2026-10-29 {$time}", 'Europe/Brussels'));

    $component = Livewire::test(Home::class);

    expect(homeHtml($component)->querySelector('h1')->textContent)->toStartWith("{$greeting},")
        ->and(homeTodayLine($component))->toBe($line);
})->with([
    ['08:00', 'Good morning', 'all clear today. go enjoy your morning'],
    ['14:00', 'Good afternoon', 'all clear today. go enjoy your afternoon'],
    ['20:00', 'Good evening', 'all clear today. go enjoy your evening'],
]);

it('reads a yearly subscription\'s real charge', function () {
    $user = homeSignedInUser();

    Subscription::factory()->for($user)->create(['name' => 'Domain', 'price' => '120.00', 'cycle' => SubscriptionCycle::Yearly, 'next_payment_date' => today()->addDay()]);

    expect(homeComingUpRows(Livewire::test(Home::class)))->toBe(['Domain | pays Fri 30 Oct | €120.00']);
});

it('shows the store and no badge for an item still linked to an expired promo', function () {
    $user = homeSignedInUser();
    $promo = Promo::factory()->for($user)->create(['deal' => '1+1', 'valid_until' => today()->subDay()]);

    GroceryItem::factory()->for($user)->create(['name' => 'Rice', 'store' => 'Lidl', 'promo_id' => $promo->id]);

    $component = Livewire::test(Home::class);

    expect(homeGroceryRows($component))->toBe(['Rice: Lidl'])
        ->and(homeCard($component, 'Groceries')->querySelector('button[aria-haspopup="dialog"]'))->toBeNull();
});

it('moves a ticked task after the open ones and updates the line and the aside', function () {
    homeSeededUser();

    $component = Livewire::test(Home::class)
        ->call('toggleTask', Task::where('title', 'Send CV to two companies')->sole()->id, true)
        ->assertDispatched('nav-counts-changed')
        ->assertNotDispatched('toast');

    expect(homeTodayRows($component))->toBe([
        'Return library book: School · overdue',
        'Put out the PMD bag: Home · due today',
        'Send CV to two companies: Internship · done (done)',
        'Water the plants: Home · done (done)',
    ])
        ->and(homeTodayLine($component))->toBe('two small things and you’re done')
        ->and(homeAside($component, 'Today'))->toBe('2 left');
});

it('keeps an overdue task on the card once ticked, and undoes it', function () {
    homeSeededUser();

    $task = Task::where('title', 'Return library book')->sole();
    $component = Livewire::test(Home::class)->call('toggleTask', $task->id, true);

    expect(homeTodayRows($component))->toContain('Return library book: School · done (done)')
        ->and(homeAside($component, 'Today'))->toBe('2 left');

    $component->call('toggleTask', $task->id, false);

    expect(homeTodayRows($component)[0])->toBe('Return library book: School · overdue')
        ->and(homeAside($component, 'Today'))->toBe('3 left');
});

it('toasts the next recurring task once, and a retick makes no second copy', function () {
    homeSeededUser();

    $task = Task::where('title', 'Put out the PMD bag')->sole();

    Livewire::test(Home::class)
        ->call('toggleTask', $task->id, true)
        ->assertDispatched('toast', message: 'Done. Next one: Thu 5 Nov')
        ->assertDispatched('nav-counts-changed');

    expect(Task::where('title', 'Put out the PMD bag')->count())->toBe(2);

    Livewire::test(Home::class)->call('toggleTask', $task->id, false)->assertNotDispatched('toast');
    Livewire::test(Home::class)->call('toggleTask', $task->id, true)->assertNotDispatched('toast');

    expect(Task::where('title', 'Put out the PMD bag')->count())->toBe(2);
});

it('ticks a recurring task once when the same tick is saved twice', function () {
    homeSeededUser();

    $task = Task::where('title', 'Put out the PMD bag')->sole();

    $component = Livewire::test(Home::class)
        ->call('toggleTask', $task->id, true)
        ->assertDispatched('toast', message: 'Done. Next one: Thu 5 Nov');

    $component->call('toggleTask', $task->id, true)
        ->assertNotDispatched('toast')
        ->assertDispatched('nav-counts-changed');

    expect(Task::where('title', 'Put out the PMD bag')->count())->toBe(2)
        ->and($task->fresh()->done)->toBeTrue()
        ->and(homeTodayRows($component))->toContain('Put out the PMD bag: Home · done (done)')
        ->and(homeAside($component, 'Today'))->toBe('2 left');
});

it('leaves an open task alone when it is unticked', function () {
    homeSeededUser();

    $task = Task::where('title', 'Send CV to two companies')->sole();
    $before = $task->fresh()->getAttributes();
    $count = Task::count();

    $this->travel(1)->minutes();

    $component = Livewire::test(Home::class)
        ->call('toggleTask', $task->id, false)
        ->assertNotDispatched('toast');

    expect($task->fresh()->getAttributes())->toBe($before)
        ->and(Task::count())->toBe($count)
        ->and(homeTodayRows($component)[1])->toBe('Send CV to two companies: Internship · due today')
        ->and(homeAside($component, 'Today'))->toBe('3 left');
});

it('reads all clear once every task is ticked', function () {
    homeSeededUser();

    $component = Livewire::test(Home::class);

    foreach (['Return library book', 'Send CV to two companies', 'Put out the PMD bag'] as $title) {
        $component->call('toggleTask', Task::where('title', $title)->sole()->id, true);
    }

    expect(homeTodayLine($component))->toBe('all clear today. go enjoy your afternoon')
        ->and(homeAside($component, 'Today'))->toBe('0 left');
});

it('takes a ticked item off the card and brings in the next one', function () {
    homeSeededUser();

    $coffee = GroceryItem::where('name', 'Coffee beans')->sole();

    $component = Livewire::test(Home::class)
        ->call('tickItem', $coffee->id)
        ->assertDispatched('nav-counts-changed')
        ->assertNotDispatched('toast');

    expect($coffee->fresh()->done)->toBeTrue()
        ->and(homeGroceryRows($component))->toBe([
            'Dish soap: −30% · Delhaize',
            'Oat milk: Aldi',
            'Spinach: Lidl',
            'Greek yoghurt: 2nd ½ price · Lidl',
            'Laundry pods: −25% · Colruyt',
        ])
        ->and(homeChipTexts($component)[0])->toBe('5 to buy')
        ->and(homeText(homeDealItems($component)[1]))->not->toContain('on list');
});

it('leaves a done item alone, with no event', function () {
    $user = homeSignedInUser();
    $item = GroceryItem::factory()->for($user)->create(['done' => true]);
    $updatedAt = $item->fresh()->updated_at;

    $this->travel(1)->minutes();

    Livewire::test(Home::class)
        ->call('tickItem', $item->id)
        ->assertNotDispatched('nav-counts-changed')
        ->assertNotDispatched('toast');

    expect($item->fresh()->done)->toBeTrue()
        ->and($item->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('gives a 404 for another user\'s task and leaves it unchanged', function () {
    homeSignedInUser();

    $theirs = Task::factory()->create(['due_date' => today()]);

    Livewire::test(Home::class)->call('toggleTask', $theirs->id, true)->assertNotFound();

    expect($theirs->fresh()->done)->toBeFalse();
});

it('gives a 404 for another user\'s item and leaves it unchanged', function () {
    homeSignedInUser();

    $theirs = GroceryItem::factory()->create();

    Livewire::test(Home::class)->call('tickItem', $theirs->id)->assertNotFound();

    expect($theirs->fresh()->done)->toBeFalse();
});

it('toggles only this user\'s nudge with a toast, and the bell shows it', function () {
    $user = homeSignedInUser();
    $other = User::factory()->create();

    $component = Livewire::test(Home::class);
    $bell = homeBell($component);

    expect($bell->getAttribute('type'))->toBe('button')
        ->and($bell->getAttribute('wire:click'))->toBe('toggleNudge')
        ->and($bell->getAttribute('aria-pressed'))->toBe('true')
        ->and($bell->innerHTML)->not->toContain('m2 2 20 20');

    $component->call('toggleNudge')->assertDispatched('toast', message: 'Morning nudge off');

    expect($user->fresh()->morning_nudge)->toBeFalse()
        ->and($other->fresh()->morning_nudge)->toBeTrue()
        ->and(homeBell($component)->getAttribute('aria-pressed'))->toBe('false')
        ->and(homeBell($component)->innerHTML)->toContain('m2 2 20 20');

    Livewire::test(Home::class)->call('toggleNudge')->assertDispatched('toast', message: 'Morning nudge on · 08:00');

    expect($user->fresh()->morning_nudge)->toBeTrue()
        ->and($other->fresh()->morning_nudge)->toBeTrue();
});

it('refreshes on the events the sheets dispatch', function (string $event, Closure $change, Closure $read, string $expected) {
    $user = homeSignedInUser();

    $component = Livewire::test(Home::class);

    $change($user);

    expect($read($component))->not->toBe($expected);

    $component->dispatch($event);

    expect($read($component))->toBe($expected);
})->with([
    'grocery-item-added' => ['grocery-item-added', fn (User $user) => GroceryItem::factory()->for($user)->create(), fn (Testable $component) => homeChipTexts($component)[0], '1 to buy'],
    'task-added' => ['task-added', fn (User $user) => Task::factory()->for($user)->create(['due_date' => today()]), fn (Testable $component) => homeAside($component, 'Today'), '1 left'],
    'promo-changed' => ['promo-changed', fn (User $user) => Promo::factory()->for($user)->create(['valid_until' => today()]), fn (Testable $component) => homeChipTexts($component)[1], '1 deal ending'],
]);

it('hides only the fifth polaroid on a phone', function () {
    $user = homeSignedInUser();

    Promo::factory()->count(6)->for($user)->create(['valid_until' => today()->addDay()]);

    $items = homeDealItems(Livewire::test(Home::class));

    expect($items)->toHaveCount(5)
        ->and(array_map(fn (Element $item) => [$item->classList->contains('hidden'), $item->classList->contains('wide:block')], $items))->toBe([
            [false, false],
            [false, false],
            [false, false],
            [false, false],
            [true, true],
        ]);
});

it('shows every empty line and leaves the deals card out when there is no data', function () {
    homeSignedInUser();

    $this->get('/')->assertOk();

    $component = Livewire::test(Home::class);

    expect(homeTodayLine($component))->toBe('all clear today. go enjoy your afternoon')
        ->and(homeChipTexts($component))->toBe(['0 to buy', '0 deals ending', '€0.00 / mo'])
        ->and(homeAside($component, 'Today'))->toBe('0 left')
        ->and(homeCard($component, 'Today')->querySelector('p')->textContent)->toBe('nothing due today')
        ->and(homeText(homeCard($component, 'Today')->querySelector('a')))->toBe('All tasks →')
        ->and(homeCard($component, 'Groceries')->querySelector('p')->textContent)->toBe('list is empty. nice.')
        ->and(homeText(homeCard($component, 'Groceries')->querySelector('a')))->toBe('Promo board →')
        ->and(homeCard($component, 'Coming up')->querySelector('p')->textContent)->toBe('nothing in the next two weeks')
        ->and(homeText(homeCard($component, 'Coming up')->querySelector('a')))->toBe('All subscriptions →')
        ->and(homeCard($component, 'Deals ending soon'))->toBeNull()
        ->and($component->html())->not->toContain('Deals ending soon');
});

it('says one deal ending in the singular', function () {
    $user = homeSignedInUser();

    Promo::factory()->for($user)->create(['valid_until' => today()]);

    expect(homeChipTexts(Livewire::test(Home::class))[1])->toBe('1 deal ending');
});

it('escapes every value the user typed', function () {
    $script = '<script>alert(1)</script>';
    $user = User::factory()->create(['name' => $script]);

    Task::factory()->for($user)->create(['title' => $script, 'due_date' => today()]);
    GroceryItem::factory()->for($user)->create(['name' => $script]);
    Subscription::factory()->for($user)->create(['name' => $script, 'next_payment_date' => today()]);

    $this->actingAs($user);

    expect($this->get('/')->assertOk()->getContent())->not->toContain($script)->toContain(e($script))
        ->and(Livewire::test(Home::class)->html())->not->toContain($script);
});

it('makes each Today row\'s text a button that opens the edit sheet', function () {
    homeSeededUser();

    $page = homeHtml(Livewire::test(Home::class));

    foreach (['Return library book', 'Send CV to two companies', 'Put out the PMD bag', 'Water the plants'] as $title) {
        $task = Task::where('title', $title)->sole();
        $button = $page->getElementById("task-open-{$task->id}");

        expect($button->tagName)->toBe('BUTTON')
            ->and(trim($button->closest('section')->querySelector('h2')->textContent))->toBe('Today')
            ->and($button->getAttribute('type'))->toBe('button')
            ->and($button->getAttribute('aria-haspopup'))->toBe('dialog')
            ->and($button->getAttribute('x-on:click'))->toBe("\$dispatch('show-task', { id: {$task->id} })")
            ->and($button->hasAttribute('aria-pressed'))->toBeFalse()
            ->and($button->hasAttribute('aria-label'))->toBeFalse()
            ->and($button->previousElementSibling->getAttribute('id'))->toBe("today-check-{$task->id}")
            ->and($button->querySelector('div'))->toBeNull()
            ->and($button->getAttribute('class'))->toBe('relative flex min-w-0 flex-1 cursor-pointer flex-col text-left break-words before:absolute before:inset-x-0 before:-inset-y-2')
            ->and(array_map(fn (string $class) => $button->classList->contains($class), ['cursor-pointer', 'text-left', 'relative', 'before:absolute', 'before:inset-x-0', 'before:-inset-y-2']))
            ->toBe([true, true, true, true, true, true])
            ->and(homeText($button->firstElementChild))->toBe($title);
    }
});

it('marks only a done Today row with data-done and ticks with $tick, passing only the task\'s id', function () {
    homeSeededUser();

    $card = homeCard(Livewire::test(Home::class), 'Today');
    $id = Task::where('title', 'Return library book')->sole()->id;
    $done = $card->querySelector('#today-check-'.Task::where('title', 'Water the plants')->sole()->id);
    $open = $card->querySelector("#today-check-{$id}");
    $labels = fn (Element $checkbox) => array_map(fn (string $name) => $checkbox->getAttribute($name), ['aria-pressed', 'aria-label', 'data-label', 'data-checked-label']);
    $title = fn (Element $checkbox) => $checkbox->nextElementSibling->firstElementChild;

    expect(array_map(fn (Element $row) => $row->getAttribute('wire:key'), iterator_to_array($card->querySelectorAll('[data-done]'))))
        ->toBe([$done->parentElement->getAttribute('wire:key')])
        ->and($done->parentElement->getAttribute('wire:key'))->toStartWith('today-')
        ->and($open->parentElement->hasAttribute('data-done'))->toBeFalse()
        ->and($labels($done))->toBe(['true', 'Mark not done: Water the plants', 'Complete: Water the plants', 'Mark not done: Water the plants'])
        ->and($labels($open))->toBe(['false', 'Complete: Return library book', 'Complete: Return library book', 'Mark not done: Return library book'])
        ->and($title($done)->getAttribute('class'))->toBe('text-[14px] font-semibold in-data-done:line-through in-data-done:opacity-55')
        ->and($title($open)->getAttribute('class'))->toBe($title($done)->getAttribute('class'))
        ->and($open->getAttribute('x-on:click'))->toBe("const focused = document.activeElement === \$el; \$tick((done, \$wire) => \$wire.toggleTask({$id}, done)).then(() => focused && document.getElementById('today-check-{$id}')?.focus())")
        ->and($open->getAttribute('x-on:click'))->not->toContain('library');
});

it('gives each Groceries row a tick-only checkbox with one label and no data-done', function () {
    homeSeededUser();

    $card = homeCard(Livewire::test(Home::class), 'Groceries');
    $coffee = GroceryItem::where('name', 'Coffee beans')->sole();
    $checkbox = $card->querySelector("#item-check-{$coffee->id}");

    expect($card->querySelectorAll('[data-done]'))->toHaveCount(0)
        ->and($checkbox->parentElement->getAttribute('wire:key'))->toBe("grocery-{$coffee->id}")
        ->and(array_map(fn (string $name) => $checkbox->getAttribute($name), ['aria-pressed', 'aria-label', 'data-label', 'data-checked-label']))
        ->toBe(['false', 'Tick off Coffee beans', 'Tick off Coffee beans', 'Tick off Coffee beans'])
        ->and($checkbox->nextElementSibling->getAttribute('class'))->toBe('min-w-0 flex-1 text-[14px] font-semibold break-words in-data-done:line-through in-data-done:opacity-55')
        ->and($checkbox->getAttribute('x-on:click'))->toBe("const focused = document.activeElement === \$el, card = \$el.closest('section'), boxes = () => [...card.querySelectorAll('button[aria-pressed]')], place = boxes().indexOf(\$el); \$tick((done, \$wire) => \$wire.tickItem({$coffee->id}), { tickOnly: true }).then(() => focused && (boxes()[place] ?? boxes().at(-1) ?? card.querySelector('a')).focus())")
        ->and($checkbox->getAttribute('x-on:click'))->not->toContain('Coffee');

    foreach ($card->querySelectorAll('button[aria-pressed]') as $box) {
        expect($box->getAttribute('id'))->toStartWith('item-check-')
            ->and($box->getAttribute('data-checked-label'))->toBe($box->getAttribute('data-label'))
            ->and($box->getAttribute('aria-label'))->toBe($box->getAttribute('data-label'));
    }
});

it('refreshes on task-changed and gives the saved row its focus back', function () {
    homeSeededUser();

    $task = Task::where('title', 'Send CV to two companies')->sole();
    $component = Livewire::test(Home::class);

    $task->update(['due_date' => null]);

    expect(homeAside($component, 'Today'))->toBe('3 left');

    $component->dispatch('task-changed', id: $task->id)
        ->assertJs("document.getElementById('task-open-{$task->id}')?.focus()");

    expect(homeAside($component, 'Today'))->toBe('2 left')
        ->and(homeTodayLine($component))->toBe('two small things and you’re done')
        ->and(homeTodayRows($component))->toBe([
            'Return library book: School · overdue',
            'Put out the PMD bag: Home · due today',
            'Water the plants: Home · done (done)',
        ]);
});
