<?php

use App\Enums\Section;
use App\Livewire\Pages\Groceries;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\Subscriptions;
use App\Livewire\Pages\Todo;
use App\Livewire\Sheets\SubscriptionSheet;
use App\Livewire\SidebarLinks;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Support\SidebarCounts;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->withoutVite();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * The links as label => count, with null for a link without one.
 *
 * @return array<string, string|null>
 */
function sidebarRows(Testable $component): array
{
    $rows = [];

    foreach (HTMLDocument::createFromString($component->html(), LIBXML_NOERROR)->querySelectorAll('a') as $link) {
        $count = $link->querySelector('.font-mono');

        $rows[trim($link->querySelector('.flex-1')->textContent)] = $count === null ? null : trim($count->textContent);
    }

    return $rows;
}

it('counts the seeded data', function () {
    $this->seed();

    expect(SidebarCounts::for(User::where('email', 'ivayla@example.com')->sole()))->toBe([
        'groceries' => 6,
        'promos' => 6,
        'todo' => 6,
        'subscriptions' => 5,
    ]);
});

it('leaves out an expired promo, a done item, a done task and a cancelled subscription', function () {
    Promo::factory()->for($this->user)->create(['valid_until' => today()->subDay()]);
    GroceryItem::factory()->for($this->user)->create(['done' => true]);
    Task::factory()->for($this->user)->create(['done' => true, 'completed_at' => now()]);
    Subscription::factory()->for($this->user)->create(['cancelled_at' => today()->subDay()]);

    expect(SidebarCounts::for($this->user))->toBe(['groceries' => 0, 'promos' => 0, 'todo' => 0, 'subscriptions' => 0]);
});

it('counts an undated open task, a future one, a promo on its last day and a trial', function () {
    Task::factory()->for($this->user)->create(['due_date' => null]);
    Task::factory()->for($this->user)->create(['due_date' => today()->addDays(30)]);
    Promo::factory()->for($this->user)->create(['valid_until' => today()]);
    Subscription::factory()->for($this->user)->create(['is_trial' => true]);

    expect(SidebarCounts::for($this->user))->toBe(['groceries' => 0, 'promos' => 1, 'todo' => 2, 'subscriptions' => 1]);
});

it('leaves out another user\'s rows', function () {
    GroceryItem::factory()->create();
    Promo::factory()->create();
    Task::factory()->create();
    Subscription::factory()->create();

    expect(SidebarCounts::for($this->user))->toBe(['groceries' => 0, 'promos' => 0, 'todo' => 0, 'subscriptions' => 0]);
});

it('renders the five links, a count on each but Home, including zero', function () {
    GroceryItem::factory()->count(2)->for($this->user)->create();

    expect(sidebarRows(Livewire::test(SidebarLinks::class, ['current' => Section::Home])))->toBe([
        'Home' => null,
        'Groceries' => '2',
        'Promo board' => '0',
        'To-do' => '0',
        'Subscriptions' => '0',
    ]);
});

it('marks the section it was given as current', function (Section $section) {
    $page = HTMLDocument::createFromString(Livewire::test(SidebarLinks::class, ['current' => $section])->html(), LIBXML_NOERROR);
    $current = $page->querySelectorAll('[aria-current="page"]');

    expect($current)->toHaveCount(1)
        ->and($current->item(0)->getAttribute('href'))->toBe(route($section->value));
})->with(Section::cases());

it('keeps its section after a refresh', function () {
    $component = Livewire::test(SidebarLinks::class, ['current' => Section::Todo])->dispatch('nav-counts-changed');

    $current = HTMLDocument::createFromString($component->html(), LIBXML_NOERROR)->querySelector('[aria-current="page"]');

    expect($current->getAttribute('href'))->toBe(route('todo'));
});

it('does not let the client change its section', function () {
    Livewire::test(SidebarLinks::class, ['current' => Section::Home])->set('current', Section::Todo);
})->throws(CannotUpdateLockedPropertyException::class);

it('updates its numbers on each event', function (string $event, Closure $change, string $label, string $count) {
    $component = Livewire::test(SidebarLinks::class, ['current' => Section::Home]);

    $change($this->user);

    expect(sidebarRows($component)[$label])->toBe('0');

    $component->dispatch($event);

    expect(sidebarRows($component)[$label])->toBe($count);
})->with([
    'nav-counts-changed' => ['nav-counts-changed', fn (User $user) => Subscription::factory()->for($user)->create(), 'Subscriptions', '1'],
    'grocery-item-added' => ['grocery-item-added', fn (User $user) => GroceryItem::factory()->for($user)->create(), 'Groceries', '1'],
    'task-added' => ['task-added', fn (User $user) => Task::factory()->for($user)->create(), 'To-do', '1'],
    'promo-changed' => ['promo-changed', fn (User $user) => Promo::factory()->for($user)->create(), 'Promo board', '1'],
]);

it('shows counts in the sidebar and none in the floating nav', function () {
    GroceryItem::factory()->count(3)->for($this->user)->create();

    $navs = HTMLDocument::createFromString($this->get('/')->getContent(), LIBXML_NOERROR)->querySelectorAll('nav[aria-label="Main"]');

    $sidebarCount = array_filter(
        iterator_to_array($navs->item(0)->querySelectorAll('a')),
        fn (Element $link) => $link->getAttribute('href') === route('groceries'),
    );

    expect(trim(array_values($sidebarCount)[0]->querySelector('.font-mono')->textContent))->toBe('3')
        ->and($navs->item(1)->querySelector('.font-mono'))->toBeNull()
        ->and($navs->item(1)->textContent)->not->toContain('3');
});

it('dispatches nav-counts-changed after each action that changes a count', function (Closure $action) {
    $action($this->user)->assertDispatched('nav-counts-changed');
})->with([
    'Home toggleTask' => fn (User $user) => Livewire::test(Home::class)->call('toggleTask', Task::factory()->for($user)->create(['due_date' => today()])->id),
    'Home tickItem' => fn (User $user) => Livewire::test(Home::class)->call('tickItem', GroceryItem::factory()->for($user)->create()->id),
    'Groceries add' => fn () => Livewire::test(Groceries::class)->set('quickItem', 'milk')->call('add'),
    'Groceries toggle' => fn (User $user) => Livewire::test(Groceries::class)->call('toggle', GroceryItem::factory()->for($user)->create()->id),
    'Groceries remove' => fn (User $user) => Livewire::test(Groceries::class)->call('remove', GroceryItem::factory()->for($user)->create()->id),
    'Groceries clearTicked' => function (User $user) {
        GroceryItem::factory()->for($user)->create(['done' => true]);

        return Livewire::test(Groceries::class)->call('clearTicked');
    },
    'Todo add' => fn () => Livewire::test(Todo::class)->set('quickTask', 'stamps today')->call('add'),
    'Todo toggle' => fn (User $user) => Livewire::test(Todo::class)->call('toggle', Task::factory()->for($user)->create()->id),
    'Subscriptions cancelTrial' => fn (User $user) => Livewire::test(Subscriptions::class)->call('cancelTrial', Subscription::factory()->for($user)->create(['is_trial' => true])->id),
    'Subscriptions cancel' => fn (User $user) => Livewire::test(Subscriptions::class)->call('cancel', Subscription::factory()->for($user)->create()->id),
    'Subscriptions resume' => fn (User $user) => Livewire::test(Subscriptions::class)->call('resume', Subscription::factory()->for($user)->create(['cancelled_at' => today()])->id),
    'SubscriptionSheet deleteSubscription' => fn (User $user) => Livewire::test(SubscriptionSheet::class)
        ->dispatch('show-subscription', id: Subscription::factory()->for($user)->create()->id)
        ->call('deleteSubscription'),
]);

it('dispatches nothing when an action changed nothing', function (Closure $action) {
    $action($this->user)->assertNotDispatched('nav-counts-changed');
})->with([
    'Groceries add with nothing typed' => fn () => Livewire::test(Groceries::class)->set('quickItem', '  ')->call('add'),
    'Groceries clearTicked with nothing ticked' => fn () => Livewire::test(Groceries::class)->call('clearTicked'),
    'Todo add with nothing typed' => fn () => Livewire::test(Todo::class)->set('quickTask', '  ')->call('add'),
    'Subscriptions cancelTrial on a paying one' => fn (User $user) => Livewire::test(Subscriptions::class)->call('cancelTrial', Subscription::factory()->for($user)->create()->id),
    'Subscriptions cancel on a cancelled one' => fn (User $user) => Livewire::test(Subscriptions::class)->call('cancel', Subscription::factory()->for($user)->create(['cancelled_at' => today()])->id),
    'Subscriptions resume on a live one' => fn (User $user) => Livewire::test(Subscriptions::class)->call('resume', Subscription::factory()->for($user)->create()->id),
    'SubscriptionSheet save' => fn (User $user) => Livewire::test(SubscriptionSheet::class)
        ->dispatch('show-subscription', id: Subscription::factory()->for($user)->create()->id)
        ->set('editSubscriptionName', 'Renamed')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('subscription-changed'),
    'SubscriptionSheet deleteSubscription with nothing loaded' => fn () => Livewire::test(SubscriptionSheet::class)->call('deleteSubscription'),
]);

it('dispatches nothing from the actions that leave every count alone', function () {
    $trial = Subscription::factory()->for($this->user)->create(['is_trial' => true, 'next_payment_date' => today()->addDays(3)]);

    Livewire::test(Subscriptions::class)->call('toggleReminder', $trial->id)->assertNotDispatched('nav-counts-changed');
    Livewire::test(Subscriptions::class)->call('keepTrial', $trial->id)->assertNotDispatched('nav-counts-changed');
    Livewire::test(Home::class)->call('toggleNudge')->assertNotDispatched('nav-counts-changed');
    Livewire::test(Todo::class)->call('toggleNudge')->assertNotDispatched('nav-counts-changed');
});
