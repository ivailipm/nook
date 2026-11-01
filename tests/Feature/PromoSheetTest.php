<?php

use App\Actions\AddGroceryItem;
use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Livewire\Pages\Groceries;
use App\Livewire\Pages\PromoBoard;
use App\Livewire\Sheets\PromoSheet;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\User;
use Database\Seeders\GroceryItemSeeder;
use Database\Seeders\PromoSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    app(GroceryItemSeeder::class)->run($this->user);
    app(PromoSeeder::class)->run($this->user);
});

function sheetPromo(User $user, string $product): Promo
{
    return $user->promos()->where('product', $product)->sole();
}

function sheetItem(User $user, string $name): GroceryItem
{
    return $user->groceryItems()->where('name', $name)->sole();
}

function openPromoSheet(Promo $promo): Testable
{
    return Livewire::test(PromoSheet::class)->dispatch('show-promo', id: $promo->id);
}

function promoSheetHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * The sheet's action buttons, by their text.
 *
 * @return list<string>
 */
function promoSheetButtons(Testable $component): array
{
    return array_map(
        fn (Element $button) => trim($button->textContent),
        iterator_to_array(promoSheetHtml($component)->querySelectorAll('button[wire\:click]')),
    );
}

/**
 * The three text lines under the big polaroid.
 *
 * @return list<string>
 */
function promoSheetLines(Testable $component): array
{
    return array_map(
        fn (Element $line) => trim(preg_replace('/\s+/u', ' ', $line->textContent)),
        iterator_to_array(promoSheetHtml($component)->querySelectorAll('[class~="text-cream/78"] > span')),
    );
}

it('opens on show-promo with a labelled title and the close wrapper', function () {
    $component = openPromoSheet(sheetPromo($this->user, 'Coffee beans 1 kg'))
        ->assertDispatched('open-sheet', name: 'promo');
    $page = promoSheetHtml($component);
    $dialog = $page->querySelector('dialog#sheet-promo');

    expect(trim($page->getElementById($dialog->getAttribute('aria-labelledby'))->textContent))->toBe('The deal')
        ->and($dialog->parentElement->getAttribute('x-on:close.capture'))->toBe('$wire.clear()');
});

it('renders nothing until a promo is shown', function () {
    $component = Livewire::test(PromoSheet::class);

    expect(promoSheetButtons($component))->toBe([])
        ->and(promoSheetLines($component))->toBe([]);
});

it('gives a 404 and does not open for another user\'s promo', function () {
    Livewire::test(PromoSheet::class)
        ->dispatch('show-promo', id: Promo::factory()->create()->id)
        ->assertNotFound()
        ->assertNotDispatched('open-sheet');
});

it('locks the promo id against the browser', function () {
    expect(fn () => openPromoSheet(sheetPromo($this->user, 'Dish soap'))->set('promoId', Promo::factory()->create()->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('shows a linked promo with Got it, Unlink and Delete', function () {
    $component = openPromoSheet(sheetPromo($this->user, 'Coffee beans 1 kg'));

    expect(promoSheetLines($component))->toBe(['Colruyt · valid 27 Oct – Sat', '€11.98 for three', 'On your list: Coffee beans (1kg)'])
        ->and(promoSheetButtons($component))->toBe(['Got it · tick it off', 'Unlink', 'Delete promo']);
});

it('shows an unlinked active promo with Add and Delete', function () {
    $component = openPromoSheet(sheetPromo($this->user, 'Pasta 1 kg'));

    expect(promoSheetLines($component))->toBe(['Aldi · valid today – Wed', 'instead of €2.39', 'Not on your list yet'])
        ->and(promoSheetButtons($component))->toBe(['Add to my list', 'Delete promo']);
});

it('shows an expired promo with Delete only', function () {
    $component = openPromoSheet(sheetPromo($this->user, 'Mandarins 2 kg'));

    expect(promoSheetLines($component))->toBe(['Aldi · valid 21 Oct – 27 Oct', 'Not on your list yet'])
        ->and(promoSheetButtons($component))->toBe(['Delete promo']);
});

it('leaves out an empty note', function () {
    expect(promoSheetLines(openPromoSheet(sheetPromo($this->user, 'Greek yoghurt'))))
        ->toBe(['Lidl · valid yesterday – Tue', 'On your list: Greek yoghurt (2)']);
});

it('leaves the qty out of the list line when the item has none', function () {
    expect(promoSheetLines(openPromoSheet(sheetPromo($this->user, 'Dish soap')))[2])->toBe('On your list: Dish soap');
});

it('does not count a ticked linked item as on the list', function () {
    sheetItem($this->user, 'Coffee beans')->update(['done' => true]);

    $component = openPromoSheet(sheetPromo($this->user, 'Coffee beans 1 kg'));

    expect(promoSheetLines($component)[2])->toBe('Not on your list yet')
        ->and(promoSheetButtons($component))->toBe(['Add to my list', 'Delete promo']);
});

it('does not count another user\'s item linked to the promo', function () {
    $promo = sheetPromo($this->user, 'Pasta 1 kg');
    GroceryItem::factory()->create(['done' => false, 'promo_id' => $promo->id]);

    expect(promoSheetLines(openPromoSheet($promo))[2])->toBe('Not on your list yet');
});

it('shows the store, product, deal and until label on the big polaroid in the tone\'s colours', function () {
    $page = promoSheetHtml(openPromoSheet(sheetPromo($this->user, 'Dish soap')));
    $area = $page->querySelector('dialog [class~="aspect-[4/3]"]');
    $frame = $area->parentElement;

    expect($area->getAttribute('class'))->toBe('relative flex aspect-[4/3] flex-col gap-1.5 overflow-hidden p-4 wrap-anywhere bg-tone-1-bg text-tone-1-text')
        ->and($frame->getAttribute('class'))->toBe('-rotate-1 rounded-polaroid bg-paper px-2.5 pt-2.5 pb-2 shadow-[0_12px_26px_rgb(0_0_0/0.45)]')
        ->and(array_map(fn (Element $line) => [trim($line->textContent), $line->getAttribute('class')], iterator_to_array($area->querySelectorAll('span'))))->toBe([
            ['Delhaize', 'relative text-[11px] font-bold tracking-[0.14em] uppercase'],
            ['Dish soap', 'relative font-display text-[26px] leading-[1.1] font-semibold'],
            ['−30%', 'relative font-display text-[40px] leading-none font-bold'],
        ])
        ->and(trim($frame->lastElementChild->textContent))->toBe('ends tomorrow')
        ->and($frame->lastElementChild->getAttribute('class'))->toBe('pt-1 font-script text-[20px] font-bold text-ends-soon')
        ->and($area->querySelector('img'))->toBeNull();
});

it('shows a photo uncropped on black, with the text on top', function () {
    $promo = sheetPromo($this->user, 'Dish soap');
    $promo->update(['photo_path' => 'promos/photo.jpg']);

    $image = promoSheetHtml(openPromoSheet($promo))->querySelector('dialog img');

    expect($image->getAttribute('src'))->toBe(route('promos.photo', $promo))
        ->and($image->getAttribute('alt'))->toBe('Photo of the Delhaize deal')
        ->and($image->getAttribute('class'))->toBe('absolute inset-0 size-full bg-black object-contain');
});

it('ticks only the first open linked item with Got it', function () {
    $promo = sheetPromo($this->user, 'Coffee beans 1 kg');
    $second = GroceryItem::factory()->for($this->user)->create(['name' => 'More coffee', 'done' => false, 'promo_id' => $promo->id]);

    openPromoSheet($promo)
        ->call('gotIt')
        ->assertDispatched('toast', message: 'Coffee beans ticked off')
        ->assertDispatched('close-sheet', name: 'promo')
        ->assertDispatched('promo-changed')
        ->assertSet('promoId', null);

    expect(sheetItem($this->user, 'Coffee beans')->done)->toBeTrue()
        ->and(sheetItem($this->user, 'Coffee beans')->promo_id)->toBe($promo->id)
        ->and($second->fresh()->done)->toBeFalse();
});

it('unlinks only the first open linked item, with no toast', function () {
    $promo = sheetPromo($this->user, 'Coffee beans 1 kg');
    $second = GroceryItem::factory()->for($this->user)->create(['name' => 'More coffee', 'done' => false, 'promo_id' => $promo->id]);

    openPromoSheet($promo)
        ->call('unlink')
        ->assertNotDispatched('toast')
        ->assertDispatched('close-sheet', name: 'promo')
        ->assertDispatched('promo-changed')
        ->assertSet('promoId', null);

    expect(sheetItem($this->user, 'Coffee beans')->promo_id)->toBeNull()
        ->and(sheetItem($this->user, 'Coffee beans')->done)->toBeFalse()
        ->and($second->fresh()->promo_id)->toBe($promo->id);
});

it('does not touch a second item on a double tap', function (string $action) {
    $promo = sheetPromo($this->user, 'Coffee beans 1 kg');
    $second = GroceryItem::factory()->for($this->user)->create(['done' => false, 'promo_id' => $promo->id]);

    openPromoSheet($promo)->call($action)->call($action);

    expect($second->fresh()->done)->toBeFalse()
        ->and($second->fresh()->promo_id)->toBe($promo->id);
})->with(['gotIt', 'unlink']);

it('adds an item with the deal linked', function () {
    $promo = sheetPromo($this->user, 'Pasta 1 kg');

    // An older promo whose product also holds "Pasta": the search would pick it, the sheet's promo must win.
    $older = $this->user->promos()->orderBy('id')->first();
    $older->update(['product' => 'Pasta shells', 'store' => Store::Colruyt]);

    openPromoSheet($promo)
        ->call('addToList')
        ->assertDispatched('toast', message: 'Pasta added with the deal linked')
        ->assertDispatched('close-sheet', name: 'promo')
        ->assertDispatched('promo-changed')
        ->assertSet('promoId', null);

    $item = sheetItem($this->user, 'Pasta');

    expect([$item->store, $item->category, $item->qty, $item->done, $item->promo_id])
        ->toBe([Store::Aldi, GroceryCategory::Pantry, null, false, $promo->id]);
});

it('adds only one item on a double tap', function () {
    openPromoSheet(sheetPromo($this->user, 'Pasta 1 kg'))->call('addToList')->call('addToList');

    expect($this->user->groceryItems()->where('name', 'Pasta')->count())->toBe(1);
});

it('adds only one item from a sheet gone stale', function () {
    $promo = sheetPromo($this->user, 'Pasta 1 kg');
    $component = openPromoSheet($promo);

    app(AddGroceryItem::class)->handle($this->user, 'Pasta', null, Store::Aldi, GroceryCategory::Pantry, $promo);

    $component->call('addToList')
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet');

    expect($this->user->groceryItems()->where('name', 'Pasta')->count())->toBe(1);
});

it('creates nothing when adding from an expired promo', function () {
    $count = $this->user->groceryItems()->count();

    openPromoSheet(sheetPromo($this->user, 'Mandarins 2 kg'))
        ->call('addToList')
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet')
        ->assertNotDispatched('promo-changed');

    expect($this->user->groceryItems()->count())->toBe($count);
});

it('creates nothing when the promo expired while the sheet was open', function () {
    $count = $this->user->groceryItems()->count();
    $component = openPromoSheet(sheetPromo($this->user, 'Pasta 1 kg'));

    $this->travelTo(Carbon::parse('2026-11-05 12:00', 'Europe/Brussels'));

    $component->call('addToList')->assertNotDispatched('toast');

    expect($this->user->groceryItems()->count())->toBe($count);
});

it('does nothing when no promo is loaded', function (string $action) {
    Livewire::test(PromoSheet::class)
        ->call($action)
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet')
        ->assertNotDispatched('promo-changed');
})->with(['gotIt', 'unlink', 'addToList', 'deletePromo']);

it('does nothing to Got it and Unlink when no open item is linked any more', function (string $action) {
    $promo = sheetPromo($this->user, 'Coffee beans 1 kg');
    $component = openPromoSheet($promo);

    sheetItem($this->user, 'Coffee beans')->update(['done' => true]);

    $component->call($action)
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet');

    expect(sheetItem($this->user, 'Coffee beans')->promo_id)->toBe($promo->id);
})->with(['gotIt', 'unlink']);

it('deletes the promo and its photo and unlinks its items', function () {
    Storage::fake(Promo::PHOTO_DISK);
    Storage::disk(Promo::PHOTO_DISK)->put('promos/photo.jpg', 'bytes');

    $promo = sheetPromo($this->user, 'Coffee beans 1 kg');
    $promo->update(['photo_path' => 'promos/photo.jpg']);

    openPromoSheet($promo)
        ->call('deletePromo')
        ->assertDispatched('toast', message: 'Promo deleted')
        ->assertDispatched('close-sheet', name: 'promo')
        ->assertDispatched('promo-changed')
        ->assertSet('promoId', null);

    expect(Promo::find($promo->id))->toBeNull()
        ->and(sheetItem($this->user, 'Coffee beans')->promo_id)->toBeNull();

    Storage::disk(Promo::PHOTO_DISK)->assertMissing('promos/photo.jpg');
});

it('renders nothing and does nothing when the promo is gone from a stale sheet', function () {
    $promo = sheetPromo($this->user, 'Pasta 1 kg');
    $component = openPromoSheet($promo);

    $promo->delete();

    $component->call('addToList')->assertNotDispatched('toast');

    expect(promoSheetButtons($component))->toBe([]);
});

it('forgets the promo when the sheet closes', function () {
    $component = openPromoSheet(sheetPromo($this->user, 'Pasta 1 kg'))->call('clear')->assertSet('promoId', null);

    expect(promoSheetButtons($component))->toBe([]);
});

it('refreshes the board on promo-changed', function () {
    $item = sheetItem($this->user, 'Coffee beans');
    $component = Livewire::test(PromoBoard::class);
    $polaroid = fn () => HTMLDocument::createFromString($component->html(), LIBXML_NOERROR)
        ->querySelector('button[aria-label^="Coffee beans 1 kg"]')->getAttribute('aria-label');

    expect($polaroid())->toEndWith(', on list');

    $item->update(['done' => true]);
    $component->dispatch('promo-changed');

    expect($polaroid())->not->toEndWith(', on list');
});

it('refreshes the list on promo-changed', function () {
    $item = sheetItem($this->user, 'Coffee beans');
    $component = Livewire::test(Groceries::class);
    $badge = fn () => HTMLDocument::createFromString($component->html(), LIBXML_NOERROR)
        ->querySelector('button[aria-label="Show the Colruyt deal: 2+1 free"]');

    expect($badge())->not->toBeNull();

    $item->update(['promo_id' => null]);
    $component->dispatch('promo-changed');

    expect($badge())->toBeNull();
});

it('gives the validity line with and without a start date', function () {
    $promo = sheetPromo($this->user, 'Coffee beans 1 kg');

    expect($promo->validityLine())->toBe('Colruyt · valid 27 Oct – Sat');

    $promo->update(['valid_from' => null]);

    expect($promo->validityLine())->toBe('Colruyt · valid until Sat');
});

it('strips the trailing size for the item name', function (?string $product, string $name) {
    expect(Promo::factory()->make(['product' => $product, 'store' => Store::Lidl])->listItemName())->toBe($name);
})->with([
    ['Coffee beans 1 kg', 'Coffee beans'],
    ['Avocados ×4', 'Avocados'],
    ['Pasta 1 kg', 'Pasta'],
    ['Mandarins 2 kg', 'Mandarins'],
    ['Dish soap', 'Dish soap'],
    ['Milk 1l', 'Milk'],
    ['Cheese 300g', 'Cheese'],
    ['Eggs x12', 'Eggs'],
    ['1 kg', '1 kg'],
    [null, 'Lidl deal'],
]);

it('links the given promo and adopts its store without searching', function () {
    $promo = sheetPromo($this->user, 'Laundry pods');

    $item = app(AddGroceryItem::class)->handle($this->user, 'Coffee', '2', Store::Lidl, GroceryCategory::Pantry, $promo);

    expect($item->promo_id)->toBe($promo->id)
        ->and($item->store)->toBe(Store::Colruyt)
        ->and($item->promo->is($promo))->toBeTrue();
});

it('still searches when no promo is given', function () {
    $item = app(AddGroceryItem::class)->handle($this->user, 'Coffee', '2', Store::Lidl, GroceryCategory::Pantry);

    expect($item->promo_id)->toBe(sheetPromo($this->user, 'Coffee beans 1 kg')->id);
});
