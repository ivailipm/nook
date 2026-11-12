<?php

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Livewire\Pages\Groceries;
use App\Livewire\Pages\PromoBoard;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\User;
use Database\Seeders\GroceryItemSeeder;
use Database\Seeders\PromoSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function groceriesHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * Each card as "Title · meta", with its rows as "Name: sub-line" and " (done)" after a ticked one.
 *
 * @return array<string, list<string>>
 */
function groceryCards(Testable $component): array
{
    $cards = [];

    foreach (groceriesHtml($component)->querySelectorAll('section') as $card) {
        $heading = $card->querySelector('h2');
        $rows = array_map(function (Element $checkbox) {
            $text = $checkbox->nextElementSibling;
            $row = trim($text->firstElementChild->textContent).': '.trim($text->lastElementChild->textContent);

            return $checkbox->getAttribute('aria-pressed') === 'true' ? "{$row} (done)" : $row;
        }, iterator_to_array($card->querySelectorAll('button[aria-pressed]')));

        $cards[trim($heading->textContent).' · '.trim($heading->nextElementSibling->textContent)] = $rows;
    }

    return $cards;
}

function grocerySubtitle(Testable $component): string
{
    return trim(groceriesHtml($component)->querySelector('h1')->nextElementSibling->textContent);
}

function clearButton(Testable $component): ?Element
{
    $buttons = array_filter(
        iterator_to_array(groceriesHtml($component)->querySelectorAll('button')),
        fn (Element $button) => str_starts_with(trim($button->textContent), 'Clear'),
    );

    return array_values($buttons)[0] ?? null;
}

/**
 * @return list<array{string, string, ?string}>
 */
function switchLinks(Testable $component): array
{
    return array_map(
        fn (Element $link) => [trim($link->textContent), $link->getAttribute('href'), $link->getAttribute('aria-current')],
        iterator_to_array(groceriesHtml($component)->querySelectorAll('nav[aria-label="Groceries"] a')),
    );
}

function seedGroceries(User $user): void
{
    app(GroceryItemSeeder::class)->run($user);
}

function groceryItem(User $user, string $name): GroceryItem
{
    return $user->groceryItems()->where('name', $name)->sole();
}

/**
 * The seeded list and promos on Thursday 29 October 2026, the prototype's base date.
 */
function seedGroceriesWithPromos(User $user): void
{
    test()->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    seedGroceries($user);
    app(PromoSeeder::class)->run($user);
}

/**
 * Each row's promo badge by item name, as [the text a phone reads, the part shown from 900px, gold], or null without one.
 * Every badge has the same classes, which grey it inside a row with data-done, so gold means its row has none.
 *
 * @return array<string, array{string, string, bool}|null>
 */
function groceryBadges(Testable $component): array
{
    $badges = [];

    foreach (groceriesHtml($component)->querySelectorAll('section button[aria-pressed]') as $checkbox) {
        $name = $checkbox->nextElementSibling;
        $badge = $name->nextElementSibling;

        if (! $badge->hasAttribute('aria-haspopup')) {
            $badges[trim($name->firstElementChild->textContent)] = null;

            continue;
        }

        $wide = $badge->querySelector('[class~="wide:inline"]');

        expect($badge->querySelector('svg[aria-hidden="true"]'))->not->toBeNull()
            ->and(array_map(fn (string $class) => $badge->classList->contains($class), ['bg-accent', 'text-on-accent', 'in-data-done:bg-white/12', 'in-data-done:text-cream/80']))
            ->toBe([true, true, true, true]);

        $badges[trim($name->firstElementChild->textContent)] = [
            trim($wide->previousSibling->textContent),
            $wide->textContent,
            ! $checkbox->parentElement->hasAttribute('data-done'),
        ];
    }

    return $badges;
}

dataset('seeded cards by store', ['the seeded list' => [[
    'Aldi · 1 left' => ['Oat milk: 2 · Dairy', 'Bananas: 6 · Fruit & veg (done)'],
    'Lidl · 2 left' => ['Spinach: 300g · Fruit & veg', 'Greek yoghurt: 2 · Dairy'],
    'Delhaize · 1 left' => ['Dish soap: Household'],
    'Colruyt · 2 left' => ['Coffee beans: 1kg · Pantry', 'Laundry pods: Household'],
]]]);

it('groups the list by store by default, hiding empty stores', function (array $cards) {
    seedGroceries($this->user);

    $component = Livewire::test(Groceries::class);
    $chips = iterator_to_array(groceriesHtml($component)->querySelectorAll('[role="group"][aria-labelledby="group-by"] button'));

    expect(groceryCards($component))->toBe($cards)
        ->and(array_map(fn (Element $chip) => [trim($chip->textContent), $chip->getAttribute('aria-pressed')], $chips))
        ->toBe([['Store', 'true'], ['Category', 'false']]);
})->with('seeded cards by store');

it('groups the list by category', function () {
    seedGroceries($this->user);

    $component = Livewire::test(Groceries::class)->set('groupBy', 'category');
    $chips = iterator_to_array(groceriesHtml($component)->querySelectorAll('[role="group"][aria-labelledby="group-by"] button'));

    expect(groceryCards($component))->toBe([
        'Fruit & veg · 1 left' => ['Spinach: 300g · Lidl', 'Bananas: 6 · Aldi (done)'],
        'Dairy · 2 left' => ['Oat milk: 2 · Aldi', 'Greek yoghurt: 2 · Lidl'],
        'Pantry · 1 left' => ['Coffee beans: 1kg · Colruyt'],
        'Household · 2 left' => ['Dish soap: Delhaize', 'Laundry pods: Colruyt'],
    ])->and(array_map(fn (Element $chip) => [trim($chip->textContent), $chip->getAttribute('aria-pressed')], $chips))
        ->toBe([['Store', 'false'], ['Category', 'true']]);
});

it('groups by store for any other group-by value', function (array $cards) {
    seedGroceries($this->user);

    expect(groceryCards(Livewire::test(Groceries::class)->set('groupBy', 'price')))->toBe($cards);
})->with('seeded cards by store');

it('shows the stores and categories in enum order and hides empty groups', function () {
    foreach ([[Store::Other, GroceryCategory::Household], [Store::Aldi, GroceryCategory::Dairy]] as [$store, $category]) {
        GroceryItem::factory()->for($this->user)->create(['name' => "{$store->value} item", 'store' => $store, 'category' => $category]);
    }

    $component = Livewire::test(Groceries::class);

    expect(array_keys(groceryCards($component)))->toBe(['Aldi · 1 left', 'Other · 1 left'])
        ->and(array_keys(groceryCards($component->set('groupBy', 'category'))))->toBe(['Dairy · 1 left', 'Household · 1 left']);
});

it('lists open items before done ones, each in the order they were added', function () {
    foreach (['Rice' => true, 'Pasta' => false, 'Flour' => true, 'Sugar' => false] as $name => $done) {
        GroceryItem::factory()->for($this->user)->create(['name' => $name, 'store' => Store::Lidl, 'category' => GroceryCategory::Pantry, 'done' => $done]);
    }

    expect(groceryCards(Livewire::test(Groceries::class)))->toBe([
        'Lidl · 2 left' => ['Pasta: Pantry', 'Sugar: Pantry', 'Rice: Pantry (done)', 'Flour: Pantry (done)'],
    ]);
});

it('reads all done when nothing in a group is open', function () {
    GroceryItem::factory()->for($this->user)->create(['name' => 'Dish soap', 'store' => Store::Delhaize, 'category' => GroceryCategory::Household, 'done' => true]);

    expect(groceryCards(Livewire::test(Groceries::class)))->toBe(['Delhaize · all done' => ['Dish soap: Household (done)']]);
});

it('marks only a done row with data-done and gives every row the same name and badge classes', function () {
    seedGroceriesWithPromos($this->user);
    $bananas = groceryItem($this->user, 'Bananas');
    $coffee = groceryItem($this->user, 'Coffee beans');
    $pods = groceryItem($this->user, 'Laundry pods');
    $coffee->update(['done' => true]);

    $page = groceriesHtml(Livewire::test(Groceries::class));
    $done = $page->getElementById("item-check-{$coffee->id}");
    $open = $page->getElementById("item-check-{$pods->id}");
    $labels = fn (Element $checkbox) => array_map(fn (string $name) => $checkbox->getAttribute($name), ['aria-pressed', 'aria-label', 'data-label', 'data-checked-label']);
    $name = fn (Element $checkbox) => $checkbox->nextElementSibling->firstElementChild;
    $badge = fn (Element $checkbox) => $checkbox->nextElementSibling->nextElementSibling;

    expect(array_map(fn (Element $row) => $row->getAttribute('wire:key'), iterator_to_array($page->querySelectorAll('[data-done]'))))
        ->toBe(["item-{$bananas->id}", "item-{$coffee->id}"])
        ->and($done->parentElement->getAttribute('wire:key'))->toBe("item-{$coffee->id}")
        ->and($open->parentElement->hasAttribute('data-done'))->toBeFalse()
        ->and($labels($done))->toBe(['true', 'Untick Coffee beans', 'Tick off Coffee beans', 'Untick Coffee beans'])
        ->and($labels($open))->toBe(['false', 'Tick off Laundry pods', 'Tick off Laundry pods', 'Untick Laundry pods'])
        ->and($name($done)->getAttribute('class'))->toBe('text-[15px] font-semibold in-data-done:line-through in-data-done:opacity-55')
        ->and($name($open)->getAttribute('class'))->toBe($name($done)->getAttribute('class'))
        ->and($done->nextElementSibling->lastElementChild->getAttribute('class'))->toBe('text-[12px] text-cream-muted')
        ->and($badge($done)->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($badge($open)->getAttribute('class'))->toBe($badge($done)->getAttribute('class'))
        ->and($page->querySelector('button[aria-label="Remove Bananas"]'))->not->toBeNull();
});

it('ticks with $tick, passing only the item\'s id', function () {
    $item = GroceryItem::factory()->for($this->user)->create(['name' => '<img src=x onerror=alert(1)>']);

    $checkbox = groceriesHtml(Livewire::test(Groceries::class))->getElementById("item-check-{$item->id}");

    expect($checkbox->getAttribute('x-on:click'))->toBe("const focused = document.activeElement === \$el; \$tick((done, \$wire) => \$wire.toggle({$item->id}, done)).then(() => focused && \$el.focus())")
        ->and($checkbox->getAttribute('x-on:click'))->not->toContain('img')
        ->and($checkbox->getAttribute('data-label'))->toBe('Tick off <img src=x onerror=alert(1)>');
});

it('counts the open items in the subtitle', function (array $done, string $subtitle) {
    foreach ($done as $isDone) {
        GroceryItem::factory()->for($this->user)->create(['done' => $isDone]);
    }

    expect(grocerySubtitle(Livewire::test(Groceries::class)))->toBe($subtitle);
})->with([
    'several' => [[false, false, true], '2 things to grab, 0 with a deal'],
    'one' => [[false, true], '1 thing to grab, 0 with a deal'],
    'only done items' => [[true], 'list is empty. nice.'],
    'nothing' => [[], 'list is empty. nice.'],
]);

it('renders the quick-add bar and its hint', function () {
    $page = groceriesHtml(Livewire::test(Groceries::class));
    $form = $page->querySelector('form');
    $input = $form->querySelector('input');

    expect($form->getAttribute('wire:submit'))->toBe('add')
        ->and(trim($page->querySelector('label[for="'.$input->id.'"]')->textContent))->toBe('Add an item')
        ->and($input->id)->toBe('quick-add-item')
        ->and($input->getAttribute('placeholder'))->toBe('2 coffee @lidl #pantry')
        ->and($input->getAttribute('autocomplete'))->toBe('off')
        ->and($input->getAttribute('maxlength'))->toBe('255')
        ->and(trim($form->querySelector('button[type="submit"]')->textContent))->toBe('Add')
        ->and(trim($form->nextElementSibling->textContent))->toBe('Number = quantity · @store · #category. Items that match a saved deal get linked automatically.');
});

it('adds a quick-add line and empties the input', function () {
    seedGroceries($this->user);

    $component = Livewire::test(Groceries::class)
        ->set('quickItem', '2 coffee @lidl #pantry')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('quickItem', '')
        ->assertDispatched('toast', message: 'Added Coffee');

    $item = $this->user->groceryItems()->latest('id')->first();

    expect([$item->name, $item->qty, $item->store, $item->category, $item->done, $item->promo_id])
        ->toBe(['Coffee', '2', Store::Lidl, GroceryCategory::Pantry, false, null])
        ->and(groceryCards($component)['Lidl · 3 left'])->toBe(['Spinach: 300g · Fruit & veg', 'Greek yoghurt: 2 · Dairy', 'Coffee: 2 · Pantry'])
        ->and(grocerySubtitle($component))->toBe('7 things to grab, 0 with a deal');

    $component->set('quickItem', '300g spinach #fru')->call('add')->assertSet('quickItem', '')->assertDispatched('toast', message: 'Added Spinach');

    expect(groceryCards($component)['Lidl · 4 left'])->toBe(['Spinach: 300g · Fruit & veg', 'Greek yoghurt: 2 · Dairy', 'Coffee: 2 · Pantry', 'Spinach: 300g · Fruit & veg']);
});

it('capitalises a quick-add name', function () {
    Livewire::test(Groceries::class)->set('quickItem', 'oat bars @aldi')->call('add')->assertDispatched('toast', message: 'Added Oat bars');

    expect($this->user->groceryItems()->sole()->name)->toBe('Oat bars');
});

it('adds nothing for a quick-add line without a name', function (string $line) {
    Livewire::test(Groceries::class)
        ->set('quickItem', $line)
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('quickItem', $line)
        ->assertNotDispatched('toast');

    expect(GroceryItem::count())->toBe(0);
})->with(['an empty line' => '', 'spaces' => '   ', 'only tokens' => '@aldi #dairy']);

it('rejects a quick-add line longer than 255 characters', function () {
    $component = Livewire::test(Groceries::class)
        ->set('quickItem', str_repeat('a', 256))
        ->call('add')
        ->assertHasErrors(['quickItem' => 'max'])
        ->assertNotDispatched('toast');

    $page = groceriesHtml($component);
    $input = $page->querySelector('#quick-add-item');
    $error = $page->querySelector('#'.$input->getAttribute('aria-describedby'));

    expect(GroceryItem::count())->toBe(0)
        ->and($input->getAttribute('aria-invalid'))->toBe('true')
        ->and(trim($error->textContent))->toBe('The item field must not be greater than 255 characters.')
        ->and($error->classList->contains('text-destructive'))->toBeTrue();

    $component->set('quickItem', str_repeat('a', 255))->call('add')->assertHasNoErrors();
    $input = groceriesHtml($component)->querySelector('#quick-add-item');

    expect($this->user->groceryItems()->sole()->name)->toBe('A'.str_repeat('a', 254))
        ->and($input->hasAttribute('aria-invalid'))->toBeFalse()
        ->and($input->hasAttribute('aria-describedby'))->toBeFalse();
});

it('ticks an item, moving it to the end of its group, and unticks it', function () {
    seedGroceries($this->user);
    $coffee = groceryItem($this->user, 'Coffee beans');

    $component = Livewire::test(Groceries::class)->call('toggle', $coffee->id, true)->assertNotDispatched('toast');

    expect($coffee->fresh()->done)->toBeTrue()
        ->and(groceryCards($component)['Colruyt · 1 left'])->toBe(['Laundry pods: Household', 'Coffee beans: 1kg · Pantry (done)'])
        ->and(grocerySubtitle($component))->toBe('5 things to grab, 0 with a deal')
        ->and(trim(clearButton($component)->textContent))->toBe('Clear 2 ticked');

    $component->call('toggle', $coffee->id, false);

    expect($coffee->fresh()->done)->toBeFalse()
        ->and(groceryCards($component)['Colruyt · 2 left'])->toBe(['Coffee beans: 1kg · Pantry', 'Laundry pods: Household'])
        ->and(grocerySubtitle($component))->toBe('6 things to grab, 0 with a deal')
        ->and(trim(clearButton($component)->textContent))->toBe('Clear 1 ticked');
});

it('sets the tapped state, so the same tap twice writes nothing the second time', function (string $name, bool $done) {
    seedGroceries($this->user);
    $item = groceryItem($this->user, $name);
    $component = Livewire::test(Groceries::class)->call('toggle', $item->id, $done);
    $updatedAt = $item->fresh()->updated_at;

    $this->travel(1)->minutes();

    $component->call('toggle', $item->id, $done)->assertDispatched('nav-counts-changed')->assertNotDispatched('toast');

    expect($item->fresh()->done)->toBe($done)
        ->and($item->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(grocerySubtitle($component))->toBe($done ? '5 things to grab, 0 with a deal' : '7 things to grab, 0 with a deal');
})->with([
    'tick twice' => ['Coffee beans', true],
    'untick twice' => ['Bananas', false],
]);

it('removes an item and hides a card left empty', function () {
    seedGroceries($this->user);
    $soap = groceryItem($this->user, 'Dish soap');

    $component = Livewire::test(Groceries::class)->call('remove', $soap->id)->assertNotDispatched('toast');

    $this->assertModelMissing($soap);

    expect(array_keys(groceryCards($component)))->toBe(['Aldi · 1 left', 'Lidl · 2 left', 'Colruyt · 2 left']);
});

it('clears every ticked item', function () {
    seedGroceries($this->user);
    $spinach = groceryItem($this->user, 'Spinach');
    $bananas = groceryItem($this->user, 'Bananas');

    $component = Livewire::test(Groceries::class)->call('toggle', $spinach->id, true);

    expect(trim(clearButton($component)->textContent))->toBe('Clear 2 ticked');

    $component->call('clearTicked')->assertNotDispatched('toast');

    $this->assertModelMissing($spinach);
    $this->assertModelMissing($bananas);

    expect(clearButton($component))->toBeNull()
        ->and(groceryCards($component)['Aldi · 1 left'])->toBe(['Oat milk: 2 · Dairy'])
        ->and($this->user->groceryItems()->count())->toBe(5);
});

it('shows no cards and no clear button once everything is cleared', function () {
    seedGroceries($this->user);
    $component = Livewire::test(Groceries::class);

    foreach ($this->user->groceryItems()->where('done', false)->pluck('id') as $id) {
        $component->call('toggle', $id, true);
    }

    $component->call('clearTicked');

    expect(groceryCards($component))->toBe([])
        ->and(clearButton($component))->toBeNull()
        ->and(grocerySubtitle($component))->toBe('list is empty. nice.');
});

it('refuses to tick or remove another user\'s item', function (string $action, array $params) {
    $theirs = GroceryItem::factory()->create(['done' => false]);

    Livewire::test(Groceries::class)->call($action, $theirs->id, ...$params)->assertNotFound();

    $this->assertModelExists($theirs);

    expect($theirs->fresh()->done)->toBeFalse();
})->with([
    'toggle' => ['toggle', [true]],
    'remove' => ['remove', []],
]);

it('clears only the user\'s ticked items', function () {
    $mine = GroceryItem::factory()->for($this->user)->create(['done' => true]);
    $theirs = GroceryItem::factory()->create(['done' => true]);

    Livewire::test(Groceries::class)->call('clearTicked');

    $this->assertModelMissing($mine);
    $this->assertModelExists($theirs);
});

it('never lists another user\'s items', function () {
    GroceryItem::factory()->for($this->user)->create(['name' => 'Milk', 'qty' => null, 'store' => Store::Lidl, 'category' => GroceryCategory::Dairy]);
    GroceryItem::factory()->create(['name' => 'Eggs', 'store' => Store::Lidl, 'category' => GroceryCategory::Dairy]);
    GroceryItem::factory()->create(['name' => 'Cheese', 'store' => Store::Other, 'done' => true]);

    $component = Livewire::test(Groceries::class);

    expect(groceryCards($component))->toBe(['Lidl · 1 left' => ['Milk: Dairy']])
        ->and(grocerySubtitle($component))->toBe('1 thing to grab, 0 with a deal')
        ->and(clearButton($component))->toBeNull();
});

it('refreshes when the add-item sheet adds an item', function () {
    $component = Livewire::test(Groceries::class);

    expect($component->effects['listeners'])->toBe(['grocery-item-added', 'promo-changed']);

    GroceryItem::factory()->for($this->user)->create(['name' => 'Oat bars', 'qty' => '4', 'store' => Store::Aldi, 'category' => GroceryCategory::Pantry]);

    expect(groceryCards($component->dispatch('grocery-item-added')))->toBe(['Aldi · 1 left' => ['Oat bars: 4 · Pantry']]);
});

it('shows the switch on the Groceries page with List current and the user\'s active promo count', function () {
    seedGroceriesWithPromos($this->user);
    Promo::factory()->create(['valid_until' => today()]);

    expect(switchLinks(Livewire::test(Groceries::class)))->toBe([
        ['List', route('groceries'), 'page'],
        ['Promo board · 6', route('promos'), null],
    ]);

    $this->user->promos()->where('valid_until', today()->addDay())->update(['valid_until' => today()->subDay()]);

    expect(switchLinks(Livewire::test(Groceries::class))[1][0])->toBe('Promo board · 5');
});

it('shows the badges and the deal count on the seeded list', function () {
    seedGroceriesWithPromos($this->user);

    $component = Livewire::test(Groceries::class);

    expect(groceryBadges($component))->toBe([
        'Oat milk' => null,
        'Bananas' => null,
        'Spinach' => null,
        'Greek yoghurt' => ['2nd ½ price', ' · Lidl', true],
        'Dish soap' => ['−30%', ' · Delhaize', true],
        'Coffee beans' => ['2+1 free', ' · Colruyt', true],
        'Laundry pods' => ['−25%', ' · Colruyt', true],
    ])->and(grocerySubtitle($component))->toBe('6 things to grab, 4 with a deal');
});

it('renders the badge between the name and the remove button', function () {
    seedGroceriesWithPromos($this->user);

    $remove = groceriesHtml(Livewire::test(Groceries::class))->querySelector('button[aria-label="Remove Coffee beans"]');
    $badge = $remove->previousElementSibling;

    expect($badge->tagName)->toBe('BUTTON')
        ->and($badge->getAttribute('aria-label'))->toBe('Show the Colruyt deal: 2+1 free')
        ->and($badge->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($badge->getAttribute('class'))->toBe('relative inline-flex max-w-[60%] cursor-pointer items-center gap-[5px] rounded-full px-2.5 py-1 text-[12px] font-bold whitespace-nowrap before:absolute before:inset-x-0 before:-inset-y-2.5 bg-accent text-on-accent in-data-done:bg-white/12 in-data-done:text-cream/80')
        ->and($badge->querySelector('[class~="wide:inline"]')->getAttribute('class'))->toBe('hidden wide:inline')
        ->and($badge->previousElementSibling->classList->contains('flex-1'))->toBeTrue();
});

it('greys a ticked item\'s badge and leaves it out of the deal count', function () {
    seedGroceriesWithPromos($this->user);

    $component = Livewire::test(Groceries::class)->call('toggle', groceryItem($this->user, 'Coffee beans')->id, true);

    expect(groceryBadges($component)['Coffee beans'])->toBe(['2+1 free', ' · Colruyt', false])
        ->and(grocerySubtitle($component))->toBe('5 things to grab, 3 with a deal');
});

it('lets a ticked item\'s badge open the promo sheet too', function () {
    seedGroceriesWithPromos($this->user);
    $coffee = $this->user->groceryItems()->where('name', 'Coffee beans')->sole();
    $coffee->update(['done' => true]);

    $badge = groceriesHtml(Livewire::test(Groceries::class))->querySelector('button[aria-label="Remove Coffee beans"]')->previousElementSibling;

    expect($badge->tagName)->toBe('BUTTON')
        ->and($badge->getAttribute('x-on:click'))->toBe("\$dispatch('show-promo', { id: {$coffee->promo_id} })");
});

it('hides the badge of a promo that has ended, before the nightly unlink', function () {
    seedGroceriesWithPromos($this->user);
    $gouda = $this->user->promos()->where('product', 'Gouda slices')->sole();
    $this->user->groceryItems()->where('name', 'Spinach')->update(['promo_id' => $gouda->id]);

    $component = Livewire::test(Groceries::class);

    expect(groceryBadges($component)['Spinach'])->toBeNull()
        ->and(grocerySubtitle($component))->toBe('6 things to grab, 4 with a deal');

    // Dish soap's promo ended on Friday 30 October, so on Saturday its badge is gone and the count drops.
    $this->travelTo(Carbon::parse('2026-10-31 00:00:01', 'Europe/Brussels'));

    $component = Livewire::test(Groceries::class);

    expect(groceryBadges($component)['Dish soap'])->toBeNull()
        ->and(grocerySubtitle($component))->toBe('6 things to grab, 3 with a deal');
});

it('adds a quick-add item with its deal badge', function () {
    seedGroceriesWithPromos($this->user);

    $component = Livewire::test(Groceries::class)
        ->set('quickItem', 'pasta')
        ->call('add')
        ->assertDispatched('toast', message: 'Added, and linked to the Aldi deal: €1.79');

    expect(groceryCards($component)['Aldi · 2 left'])->toBe(['Oat milk: 2 · Dairy', 'Pasta: Pantry', 'Bananas: 6 · Fruit & veg (done)'])
        ->and(groceryBadges($component)['Pasta'])->toBe(['€1.79', ' · Aldi', true])
        ->and(grocerySubtitle($component))->toBe('7 things to grab, 5 with a deal');
});

it('loads each row\'s promo without a query per row', function () {
    seedGroceriesWithPromos($this->user);

    $queries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Groceries::class);

        return count(DB::getQueryLog());
    };

    $before = $queries();

    foreach (['Rice', 'Flour', 'Sugar'] as $product) {
        $promo = $this->user->promos()->create(['store' => Store::Aldi, 'product' => $product, 'deal' => '€1', 'valid_until' => today()->addDays(3)]);
        GroceryItem::factory()->for($this->user)->create(['name' => $product, 'store' => Store::Aldi, 'done' => false, 'promo_id' => $promo->id]);
    }

    expect(array_filter(groceryBadges(Livewire::test(Groceries::class))))->toHaveCount(7)
        ->and($queries())->toBe($before);
});

it('shows the switch on the Promo board with the user\'s open item count', function () {
    seedGroceries($this->user);
    GroceryItem::factory()->create(['done' => false]);

    $component = Livewire::test(PromoBoard::class);

    expect(switchLinks($component))->toBe([
        ['List · 6', route('groceries'), null],
        ['Promo board', route('promos'), 'page'],
    ])->and($component->effects['listeners'])->toBe(['grocery-item-added', 'promo-changed']);

    GroceryItem::factory()->for($this->user)->create(['done' => false]);

    expect(switchLinks($component->dispatch('grocery-item-added'))[0][0])->toBe('List · 7');
});
