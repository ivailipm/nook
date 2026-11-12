<?php

use App\Actions\AddStore;
use App\Livewire\Pages\Groceries;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\PromoBoard;
use App\Livewire\Sheets\AddItem;
use App\Livewire\Sheets\AddPromo;
use App\Livewire\Sheets\Stores;
use App\Models\CustomStore;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function customStoresHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * The store chips (the toggles, not the +) in the chip row the selector finds.
 *
 * @return list<string>
 */
function customStoreChips(Testable $component, string $row): array
{
    return array_map(
        fn (Element $chip) => trim($chip->textContent),
        iterator_to_array(customStoresHtml($component)->querySelectorAll("{$row} button[aria-pressed]")),
    );
}

/**
 * The stores listed under "Added by you" in the Your stores sheet, with their remove buttons' labels.
 *
 * @return list<array{string, string}>
 */
function ownStoreRows(Testable $component): array
{
    return array_map(
        fn (Element $row) => [trim($row->querySelector('span')->textContent), $row->querySelector('button')->getAttribute('aria-label')],
        iterator_to_array(customStoresHtml($component)->querySelectorAll('ul[aria-labelledby="stores-own"] li')),
    );
}

dataset('store chip rows', [
    'the promo board filter' => [PromoBoard::class, '[role="group"][aria-label="Filter by store"]', ['All']],
    'the add-item sheet' => [AddItem::class, '[role="group"][aria-labelledby="add-item-store"]', []],
    'the add-promo sheet' => [AddPromo::class, '[role="group"][aria-labelledby="add-promo-store"]', []],
]);

it('lists the built-in stores, then the user\'s own in the order they were added, then Other', function () {
    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);
    CustomStore::factory()->for($this->user)->create(['name' => 'Albert Heijn']);
    CustomStore::factory()->create(['name' => 'Spar']);

    expect($this->user->storeNames())->toBe(['Aldi', 'Lidl', 'Delhaize', 'Colruyt', 'Carrefour', 'Albert Heijn', 'Other'])
        ->and(User::factory()->create()->storeNames())->toBe(['Aldi', 'Lidl', 'Delhaize', 'Colruyt', 'Other']);
});

it('adds a store with a squished, capitalised name', function () {
    $store = app(AddStore::class)->handle($this->user, '  albert   heijn ');

    expect([$store->name, $store->user_id])->toBe(['Albert heijn', $this->user->id])
        ->and(app(AddStore::class)->message($store))->toBe('Added Albert heijn to your stores');
});

it('refuses a name that is blank, too long, taken in any case, or All', function (string $name, string $message) {
    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);

    expect(fn () => app(AddStore::class)->handle($this->user, $name))->toThrow(ValidationException::class, $message)
        ->and($this->user->customStores()->pluck('name')->all())->toBe(['Carrefour']);
})->with([
    'blank' => ['   ', 'The store name field is required.'],
    'too long' => [str_repeat('a', 31), 'The store name field must not be greater than 30 characters.'],
    'a built-in store' => ['lidl', 'You already have Lidl.'],
    'Other' => ['OTHER', 'You already have Other.'],
    'one of the user\'s own' => ['carrefour', 'You already have Carrefour.'],
    'the filter for every store' => ['all', 'All is the filter for every store. Pick another name.'],
]);

it('takes a name another user already has, and 30 characters', function () {
    CustomStore::factory()->create(['name' => 'Carrefour']);

    app(AddStore::class)->handle($this->user, 'Carrefour');
    app(AddStore::class)->handle($this->user, str_repeat('b', 30));

    expect($this->user->customStores()->count())->toBe(2);
});

it('adds a store from the sheet and keeps it open with an empty field', function () {
    $component = Livewire::test(Stores::class)
        ->set('store', 'carrefour')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('store', '')
        ->assertSet('status', 'Added Carrefour to your stores')
        ->assertNotDispatched('toast')
        ->assertDispatched('stores-changed')
        ->assertDispatched('store-added', name: 'Carrefour')
        ->assertNotDispatched('close-sheet');

    expect(ownStoreRows($component))->toBe([['Carrefour', 'Remove Carrefour']])
        ->and(customStoresHtml($component)->querySelector('dialog#sheet-stores [role="status"]')->textContent)->toBe('Added Carrefour to your stores');
});

it('shows why a store was refused under the field and adds nothing', function () {
    $component = Livewire::test(Stores::class)
        ->set('store', 'Lidl')
        ->call('add')
        ->assertHasErrors(['store'])
        ->assertSet('status', '')
        ->assertNotDispatched('stores-changed');

    $input = customStoresHtml($component)->getElementById('store');

    expect($input->getAttribute('aria-invalid'))->toBe('true')
        ->and(trim(customStoresHtml($component)->getElementById('store-error')->textContent))->toBe('You already have Lidl.')
        ->and(CustomStore::count())->toBe(0);
});

it('clears the field, its error and the status when the sheet closes', function () {
    Livewire::test(Stores::class)
        ->set('store', 'Carrefour')
        ->call('add')
        ->set('store', 'Lidl')
        ->call('add')
        ->assertHasErrors(['store'])
        ->call('resetForm')
        ->assertHasNoErrors()
        ->assertSet('store', '')
        ->assertSet('status', '');
});

it('keeps an empty status line in the sheet, so a screen reader announces it when it fills', function () {
    $status = customStoresHtml(Livewire::test(Stores::class))->querySelector('dialog#sheet-stores [role="status"]');

    expect($status->textContent)->toBe('');
});

it('says which stores are always there and lists only the user\'s own, oldest first', function () {
    $component = Livewire::test(Stores::class);
    $page = customStoresHtml($component);

    expect($page->querySelector('dialog#sheet-stores h2')->textContent)->toBe('Your stores')
        ->and($page->querySelector('dialog#sheet-stores')->textContent)->toContain('Aldi, Lidl, Delhaize, Colruyt and Other are always there.')
        ->and($page->getElementById('stores-own'))->toBeNull();

    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);
    CustomStore::factory()->for($this->user)->create(['name' => 'Kruidvat']);
    CustomStore::factory()->create(['name' => 'Spar']);

    expect(ownStoreRows($component->call('$refresh')))->toBe([['Carrefour', 'Remove Carrefour'], ['Kruidvat', 'Remove Kruidvat']]);
});

it('removes a store and moves its items and deals to Other', function (int $items, int $deals, string $status) {
    $store = CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);
    GroceryItem::factory()->count($items)->for($this->user)->create(['store' => 'Carrefour']);
    Promo::factory()->count($deals)->for($this->user)->create(['store' => 'Carrefour']);
    $lidl = GroceryItem::factory()->for($this->user)->create(['store' => 'Lidl']);
    $theirs = GroceryItem::factory()->create(['store' => 'Carrefour']);

    $component = Livewire::test(Stores::class)
        ->call('remove', $store->id)
        ->assertSet('status', $status)
        ->assertDispatched('stores-changed');

    $this->assertModelMissing($store);

    expect($this->user->groceryItems()->where('store', 'Other')->count())->toBe($items)
        ->and($this->user->promos()->where('store', 'Other')->count())->toBe($deals)
        ->and($lidl->fresh()->store)->toBe('Lidl')
        ->and($theirs->fresh()->store)->toBe('Carrefour')
        ->and(ownStoreRows($component))->toBe([]);
})->with([
    'nothing at the store' => [0, 0, 'Removed Carrefour'],
    'one item' => [1, 0, 'Removed Carrefour · 1 item moved to Other'],
    'deals only' => [0, 2, 'Removed Carrefour · 2 deals moved to Other'],
    'items and a deal' => [2, 1, 'Removed Carrefour · 2 items and 1 deal moved to Other'],
]);

it('leaves another user\'s store and a store that is already gone alone', function () {
    $theirs = CustomStore::factory()->create(['name' => 'Spar']);

    Livewire::test(Stores::class)
        ->call('remove', $theirs->id)
        ->call('remove', $theirs->id + 1000)
        ->assertSet('status', '')
        ->assertNotDispatched('stores-changed');

    $this->assertModelExists($theirs);
});

it('ends each store chip row with a + that opens the Your stores sheet', function (string $component, string $row) {
    $button = customStoresHtml(Livewire::test($component))->querySelector("{$row} > button:last-child");

    expect($button->getAttribute('aria-label'))->toBe('Add or remove stores')
        ->and($button->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($button->hasAttribute('aria-pressed'))->toBeFalse()
        ->and($button->getAttribute('x-on:click'))->toBe("\$dispatch('open-sheet', { name: 'stores' })");
})->with('store chip rows');

it('shows the user\'s own stores between Colruyt and Other in each store chip row', function (string $component, string $row, array $first) {
    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);
    CustomStore::factory()->create(['name' => 'Spar']);

    expect(customStoreChips(Livewire::test($component), $row))->toBe([...$first, 'Aldi', 'Lidl', 'Delhaize', 'Colruyt', 'Carrefour', 'Other']);
})->with('store chip rows');

it('picks up a store added elsewhere when stores change', function (string $component, string $row) {
    $testable = Livewire::test($component);

    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);

    expect(customStoreChips($testable->dispatch('stores-changed'), $row))->toContain('Carrefour');
})->with('store chip rows');

it('sets a store with any characters in its name from its chip', function (string $component, string $row) {
    CustomStore::factory()->for($this->user)->create(['name' => 'Jo\'s "deli"']);

    $chip = customStoresHtml(Livewire::test($component))->querySelectorAll("{$row} button[aria-pressed]")->item($component === PromoBoard::class ? 5 : 4);
    $property = $component === PromoBoard::class ? 'storeFilter' : 'store';

    expect(trim($chip->textContent))->toBe('Jo\'s "deli"')
        ->and($chip->getAttribute('wire:click'))->toBe("\$set('{$property}', 'Jo\\u0027s \\u0022deli\\u0022')");
})->with('store chip rows');

it('picks a store added on top of an open add-item or add-promo sheet', function (string $component, string $name) {
    $wrapper = customStoresHtml(Livewire::test($component))->querySelector('div:has(> dialog#sheet-'.$name.')');

    expect($wrapper->getAttribute('x-on:store-added.window'))->toBe("\$el.querySelector('dialog').open && \$wire.set('store', \$event.detail.name)");
})->with([
    'add-item' => [AddItem::class, 'add-item'],
    'add-promo' => [AddPromo::class, 'add-promo'],
]);

it('adds an item at a store of the user\'s own', function () {
    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);

    Livewire::test(AddItem::class)
        ->set('itemName', 'wine')
        ->set('store', 'Carrefour')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->groceryItems()->sole()->store)->toBe('Carrefour');
});

it('pins a deal at a store of the user\'s own', function () {
    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);

    Livewire::test(AddPromo::class)
        ->set('store', 'Carrefour')
        ->call('save')
        ->assertHasNoErrors();

    $promo = $this->user->promos()->sole();

    expect([$promo->store, $promo->product])->toBe(['Carrefour', 'Carrefour deal'])
        ->and($promo->validityLine())->toStartWith('Carrefour · valid ');
});

it('refuses a store that is not one of the user\'s', function (string $component, array $fields) {
    CustomStore::factory()->create(['name' => 'Spar']);

    $testable = Livewire::test($component);

    foreach ([...$fields, 'store' => 'Spar'] as $property => $value) {
        $testable->set($property, $value);
    }

    $testable->call('save')->assertHasErrors(['store' => 'in']);

    expect($this->user->groceryItems()->count() + $this->user->promos()->count())->toBe(0);
})->with([
    'the add-item sheet' => [AddItem::class, ['itemName' => 'Milk']],
    'the add-promo sheet' => [AddPromo::class, []],
]);

it('falls back to Lidl when the picked store is removed, and keeps a store that is still there', function (string $component) {
    $carrefour = CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);
    CustomStore::factory()->for($this->user)->create(['name' => 'Kruidvat']);

    $kept = Livewire::test($component)->set('store', 'Kruidvat');
    $removed = Livewire::test($component)->set('store', 'Carrefour');

    $carrefour->delete();

    $kept->dispatch('stores-changed')->assertSet('store', 'Kruidvat');
    $removed->dispatch('stores-changed')->assertSet('store', 'Lidl');
})->with([AddItem::class, AddPromo::class]);

it('filters the board by a store of the user\'s own, and shows every store once it is removed', function () {
    $carrefour = CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);
    Promo::factory()->for($this->user)->create(['store' => 'Carrefour', 'product' => 'Red wine', 'valid_until' => today()->addDays(2)]);
    Promo::factory()->for($this->user)->create(['store' => 'Lidl', 'product' => 'Rye bread', 'valid_until' => today()->addDays(2)]);

    $component = Livewire::test(PromoBoard::class)
        ->set('storeFilter', 'Carrefour')
        ->assertSee('Red wine')
        ->assertDontSee('Rye bread');

    $carrefour->delete();

    $component->dispatch('stores-changed')->assertSee('Red wine')->assertSee('Rye bread');

    expect(customStoresHtml($component)->querySelector('[aria-label="Filter by store"] button[aria-pressed="true"]')->textContent)->toBe('All');
});

it('groups the list by the user\'s stores, with any other store an item names last', function () {
    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);

    foreach (['Spar', 'Other', 'Carrefour', 'Aldi'] as $store) {
        GroceryItem::factory()->for($this->user)->create(['store' => $store]);
    }

    $titles = array_map(
        fn (Element $heading) => trim($heading->textContent),
        iterator_to_array(customStoresHtml(Livewire::test(Groceries::class))->querySelectorAll('section h2')),
    );

    expect($titles)->toBe(['Aldi', 'Carrefour', 'Other', 'Spar']);
});

it('reads a store of the user\'s own from the quick add', function (string $line, string $store) {
    CustomStore::factory()->for($this->user)->create(['name' => 'Carrefour']);

    Livewire::test(Groceries::class)->set('quickItem', $line)->call('add');

    expect($this->user->groceryItems()->sole()->store)->toBe($store);
})->with([
    'its prefix' => ['wine @carr', 'Carrefour'],
    'a prefix a built-in store has first' => ['wine @c', 'Colruyt'],
]);

it('refreshes the list and Home when stores change', function (string $component) {
    expect(Livewire::test($component)->effects['listeners'])->toContain('stores-changed');
})->with([Groceries::class, Home::class, PromoBoard::class]);
