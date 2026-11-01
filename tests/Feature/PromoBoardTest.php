<?php

use App\Actions\AddGroceryItem;
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
use Dom\Node;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date: six active promos and three expired this week.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    app(GroceryItemSeeder::class)->run($this->user);
    app(PromoSeeder::class)->run($this->user);
});

function boardHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * The element's child elements, in order.
 *
 * @return list<Element>
 */
function boardChildren(Element $element): array
{
    return array_values(array_filter(iterator_to_array($element->childNodes), fn (Node $node) => $node instanceof Element));
}

/**
 * The frame of each polaroid in the grid.
 *
 * @return list<Element>
 */
function boardFrames(Testable $component): array
{
    return array_map(fn (Element $item) => $item->firstElementChild, iterator_to_array(boardHtml($component)->querySelectorAll('ul[role="list"] > li')));
}

/**
 * Each polaroid as [store, product, deal, label, on list].
 *
 * @return list<array{string, ?string, string, string, bool}>
 */
function boardPolaroids(Testable $component): array
{
    return array_map(function (Element $frame) {
        $photo = boardChildren($frame)[1];
        $labelRow = $frame->lastElementChild;
        $pill = $labelRow->childElementCount === 2 ? trim(preg_replace('/\s+/', ' ', $labelRow->lastElementChild->textContent)) : null;

        return [
            trim($photo->firstElementChild->textContent),
            $photo->childElementCount === 3 ? trim(boardChildren($photo)[1]->textContent) : null,
            trim($photo->lastElementChild->textContent),
            trim($labelRow->firstElementChild->textContent),
            match ($pill) {
                'on list' => true,
                null => false,
            },
        ];
    }, boardFrames($component));
}

/**
 * The products on the polaroids, in order.
 *
 * @return list<?string>
 */
function boardProducts(Testable $component): array
{
    return array_column(boardPolaroids($component), 1);
}

/**
 * @return list<array{string, string}>
 */
function boardChips(Testable $component): array
{
    return array_map(
        fn (Element $chip) => [trim($chip->textContent), $chip->getAttribute('aria-pressed')],
        iterator_to_array(boardHtml($component)->querySelectorAll('[role="group"][aria-label="Filter by store"] button')),
    );
}

function archiveToggle(Testable $component): Element
{
    $buttons = boardHtml($component)->querySelectorAll('button[wire\:click="$toggle(\'archive\')"]');

    expect($buttons)->toHaveCount(1);

    return $buttons->item(0);
}

function boardEmptyState(Testable $component): ?Element
{
    return boardHtml($component)->querySelector('body > div > p');
}

function boardPromo(User $user, string $product): Promo
{
    return $user->promos()->where('product', $product)->sole();
}

it('shows the active promos, soonest end first, with their labels and on-list pills', function () {
    expect(boardPolaroids(Livewire::test(PromoBoard::class)))->toBe([
        ['Delhaize', 'Dish soap', '−30%', 'ends tomorrow', true],
        ['Colruyt', 'Coffee beans 1 kg', '2+1 free', 'until Sat', true],
        ['Lidl', 'Avocados ×4', '€2.49', 'until Sat', false],
        ['Lidl', 'Greek yoghurt', '2nd ½ price', 'until Tue', true],
        ['Aldi', 'Pasta 1 kg', '€1.79', 'until Wed', false],
        ['Colruyt', 'Laundry pods', '−25%', 'until 10 Nov', true],
    ]);
});

it('lays out the page from the header to the archive toggle', function () {
    $root = boardHtml(Livewire::test(PromoBoard::class))->querySelector('body > div');
    $blocks = array_map(fn (Element $block) => $block->tagName, boardChildren($root));

    expect($blocks)->toBe(['DIV', 'NAV', 'BUTTON', 'DIV', 'UL', 'BUTTON'])
        ->and($root->querySelector('h1')->innerHTML)->toBe('Promo <em>board</em>')
        ->and(trim($root->querySelector('h1')->previousElementSibling->textContent))->toBe('GROCERIES')
        ->and(trim($root->querySelector('h1')->nextElementSibling->textContent))->toBe('snapped from this week’s folders')
        ->and(boardChildren($root)[3]->getAttribute('role'))->toBe('group')
        ->and(boardChildren($root)[4]->getAttribute('class'))->toBe('grid grid-cols-2 gap-x-4 gap-y-[26px] pt-2 wide:grid-cols-[repeat(auto-fill,minmax(180px,1fr))]');
});

it('renders the snap card as a button that opens the add-promo sheet, with the camera tile', function () {
    $card = boardChildren(boardHtml(Livewire::test(PromoBoard::class))->querySelector('body > div'))[2];
    $tile = $card->firstElementChild;

    expect($card->tagName)->toBe('BUTTON')
        ->and($card->getAttribute('type'))->toBe('button')
        ->and($card->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($card->getAttribute('x-on:click'))->toBe("\$dispatch('open-sheet', { name: 'add-promo' })")
        ->and($card->classList->contains('w-full'))->toBeTrue()
        ->and($card->classList->contains('text-left'))->toBeTrue()
        ->and($card->classList->contains('cursor-pointer'))->toBeTrue()
        ->and($tile->classList->contains('bg-accent'))->toBeTrue()
        ->and($tile->classList->contains('text-on-accent'))->toBeTrue()
        ->and($tile->querySelector('svg[aria-hidden="true"]'))->not->toBeNull()
        ->and(trim($card->lastElementChild->firstElementChild->textContent))->toBe('Snap or share a deal')
        ->and(trim($card->lastElementChild->lastElementChild->textContent))->toBe('Screenshot a folder page, then Share → nook. Or upload it here.');
});

it('writes the label in red when the promo ends tomorrow or today, otherwise in brown', function () {
    boardPromo($this->user, 'Pasta 1 kg')->update(['valid_until' => today()]);

    $frames = boardFrames(Livewire::test(PromoBoard::class));
    $label = fn (int $index) => $frames[$index]->lastElementChild->firstElementChild;

    expect(trim($label(0)->textContent))->toBe('ends today!')
        ->and($label(0)->classList->contains('text-ends-soon'))->toBeTrue()
        ->and(trim($label(1)->textContent))->toBe('ends tomorrow')
        ->and($label(1)->classList->contains('text-ends-soon'))->toBeTrue()
        ->and($label(1)->classList->contains('text-[#3a2a20]'))->toBeFalse()
        ->and(trim($label(2)->textContent))->toBe('until Sat')
        ->and($label(2)->classList->contains('text-[#3a2a20]'))->toBeTrue()
        ->and($label(2)->classList->contains('text-ends-soon'))->toBeFalse();
});

it('shows the store chips in order with All pressed by default', function () {
    expect(boardChips(Livewire::test(PromoBoard::class)))->toBe([
        ['All', 'true'], ['Aldi', 'false'], ['Lidl', 'false'], ['Delhaize', 'false'], ['Colruyt', 'false'], ['Other', 'false'],
    ]);
});

it('filters the board by store', function () {
    $component = Livewire::test(PromoBoard::class)->set('storeFilter', 'Lidl');

    expect(boardProducts($component))->toBe(['Avocados ×4', 'Greek yoghurt'])
        ->and(boardChips($component)[2])->toBe(['Lidl', 'true'])
        ->and(boardChips($component)[0])->toBe(['All', 'false'])
        ->and(boardEmptyState($component))->toBeNull();

    expect(boardProducts($component->set('storeFilter', 'All')))->toHaveCount(6);
});

it('sets the filter from a chip', function () {
    $chip = boardHtml(Livewire::test(PromoBoard::class))->querySelectorAll('[role="group"][aria-label="Filter by store"] button')->item(4);

    expect($chip->getAttribute('wire:click'))->toBe("\$set('storeFilter', 'Colruyt')");
});

it('shows the empty state when no promo is at the store', function () {
    $component = Livewire::test(PromoBoard::class)->set('storeFilter', 'Other');
    $empty = boardEmptyState($component);

    expect(trim($empty->textContent))->toBe('nothing here yet. snap your first deal')
        ->and($empty->getAttribute('class'))->toBe('py-5 text-center font-script text-[21px] text-cream/78')
        ->and(boardHtml($component)->querySelectorAll('li'))->toHaveCount(0)
        ->and(boardChips($component)[5])->toBe(['Other', 'true']);
});

it('shows every store for an unknown filter', function () {
    $component = Livewire::test(PromoBoard::class)->set('storeFilter', 'Carrefour');

    expect(boardProducts($component))->toHaveCount(6)
        ->and(array_column(boardChips($component), 1))->toBe(['true', 'false', 'false', 'false', 'false', 'false']);
});

it('counts this week\'s expired promos on the archive toggle', function () {
    $toggle = archiveToggle(Livewire::test(PromoBoard::class));

    expect(trim($toggle->textContent))->toBe('3 expired this week · archive')
        ->and($toggle->getAttribute('type'))->toBe('button')
        ->and($toggle->getAttribute('wire:click'))->toBe("\$toggle('archive')")
        ->and($toggle->querySelector('svg[aria-hidden="true"]'))->not->toBeNull();
});

it('counts the archive across every store', function () {
    expect(trim(archiveToggle(Livewire::test(PromoBoard::class)->set('storeFilter', 'Aldi'))->textContent))->toBe('3 expired this week · archive');
});

it('reads 0 on the toggle with nothing expired this week', function () {
    $this->user->promos()->expired()->delete();

    expect(trim(archiveToggle(Livewire::test(PromoBoard::class))->textContent))->toBe('0 expired this week · archive');
});

it('shows the archive, greyed, after toggling', function () {
    $component = Livewire::test(PromoBoard::class)->toggle('archive');
    $page = boardHtml($component);

    expect($page->querySelector('h1')->innerHTML)->toBe('The <em>archive</em>')
        ->and(trim($page->querySelector('h1')->nextElementSibling->textContent))->toBe('expired deals rest here')
        ->and(trim($page->querySelector('h1')->previousElementSibling->textContent))->toBe('GROCERIES')
        ->and(boardPolaroids($component))->toBe([
            ['Aldi', 'Mandarins 2 kg', '1+1', 'expired 27 Oct', false],
            ['Delhaize', 'Kitchen roll', '−40%', 'expired 25 Oct', false],
            ['Lidl', 'Gouda slices', '€1.99', 'expired yesterday', false],
        ])
        ->and(trim(archiveToggle($component)->textContent))->toBe('← Back to the board');

    foreach (boardFrames($component) as $frame) {
        expect($frame->classList->contains('grayscale'))->toBeTrue()
            ->and($frame->classList->contains('opacity-55'))->toBeTrue()
            ->and($frame->lastElementChild->firstElementChild->classList->contains('text-[#6b625a]'))->toBeTrue();
    }

    expect(boardProducts($component->set('storeFilter', 'Delhaize')))->toBe(['Kitchen roll']);

    $component->set('storeFilter', 'All')->toggle('archive');

    expect(boardHtml($component)->querySelector('h1')->innerHTML)->toBe('Promo <em>board</em>')
        ->and(boardProducts($component))->toHaveCount(6)
        ->and(trim(archiveToggle($component)->textContent))->toBe('3 expired this week · archive');
});

it('shows the empty state in an empty archive', function () {
    $component = Livewire::test(PromoBoard::class)->set('storeFilter', 'Colruyt')->toggle('archive');

    expect(trim(boardEmptyState($component)->textContent))->toBe('nothing here yet. snap your first deal');
});

it('leaves a promo that expired last week out of both views', function () {
    $this->user->promos()->create(['store' => Store::Aldi, 'product' => 'Old cheese', 'deal' => '−50%', 'valid_until' => '2026-10-24']);

    $component = Livewire::test(PromoBoard::class);

    expect(boardProducts($component))->not->toContain('Old cheese')
        ->and(boardProducts($component->toggle('archive')))->not->toContain('Old cheese')
        ->and(boardProducts($component))->toHaveCount(3);
});

it('drops the on-list pill when the linked item is ticked', function () {
    $coffee = $this->user->groceryItems()->where('name', 'Coffee beans')->sole();
    $component = Livewire::test(PromoBoard::class);

    expect(boardPolaroids($component)[1][4])->toBeTrue();

    Livewire::test(Groceries::class)->call('toggle', $coffee->id);

    expect(boardPolaroids($component->call('$refresh'))[1])->toBe(['Colruyt', 'Coffee beans 1 kg', '2+1 free', 'until Sat', false]);
});

it('shows no pill for a promo linked only to a done item', function () {
    $this->user->groceryItems()->where('name', 'Bananas')->update(['promo_id' => boardPromo($this->user, 'Avocados ×4')->id]);

    expect(boardPolaroids(Livewire::test(PromoBoard::class))[2])->toBe(['Lidl', 'Avocados ×4', '€2.49', 'until Sat', false]);
});

it('never shows or counts another user\'s promos or items', function () {
    $other = User::factory()->create();
    Promo::factory()->for($other)->create(['store' => Store::Lidl, 'product' => 'Their butter', 'valid_until' => today()->addDay()]);
    Promo::factory()->for($other)->create(['store' => Store::Lidl, 'product' => 'Their bread', 'valid_until' => today()->subDay()]);
    GroceryItem::factory()->for($other)->create(['done' => false, 'promo_id' => boardPromo($this->user, 'Avocados ×4')->id]);

    $component = Livewire::test(PromoBoard::class);

    expect(boardProducts($component))->toBe(['Dish soap', 'Coffee beans 1 kg', 'Avocados ×4', 'Greek yoghurt', 'Pasta 1 kg', 'Laundry pods'])
        ->and(boardPolaroids($component)[2][4])->toBeFalse()
        ->and(trim(archiveToggle($component)->textContent))->toBe('3 expired this week · archive')
        ->and(boardProducts($component->toggle('archive')))->toBe(['Mandarins 2 kg', 'Kitchen roll', 'Gouda slices']);
});

it('reads "ends today!" in red on a promo\'s last day', function () {
    $this->user->promos()->create(['store' => Store::Other, 'product' => 'Market flowers', 'deal' => '€5', 'valid_until' => today()]);

    $component = Livewire::test(PromoBoard::class)->set('storeFilter', 'Other');
    $label = boardFrames($component)[0]->lastElementChild->firstElementChild;

    expect(boardPolaroids($component))->toBe([['Other', 'Market flowers', '€5', 'ends today!', false]])
        ->and($label->classList->contains('text-ends-soon'))->toBeTrue();
});

it('leaves out the product line when a promo has no product', function () {
    $this->user->promos()->create(['store' => Store::Other, 'product' => null, 'deal' => '−20% on everything', 'valid_until' => today()->addDays(3)]);

    $component = Livewire::test(PromoBoard::class)->set('storeFilter', 'Other');

    expect(boardPolaroids($component))->toBe([['Other', null, '−20% on everything', 'until Sun', false]])
        ->and(boardChildren(boardFrames($component)[0])[1]->childElementCount)->toBe(2);
});

it('colours each polaroid with its tone', function (int $tone) {
    $this->user->promos()->delete();
    $this->user->promos()->create(['store' => Store::Aldi, 'product' => 'Rice', 'deal' => '€1', 'valid_until' => today()->addDays(3), 'tone' => $tone]);

    $frame = boardFrames(Livewire::test(PromoBoard::class))[0];
    $classes = fn (Element $element) => iterator_to_array($element->classList);

    expect($classes(boardChildren($frame)[1]))->toContain("bg-tone-{$tone}-bg", "text-tone-{$tone}-text")
        ->and($classes($frame->firstElementChild))->toContain("bg-tone-{$tone}-tape", 'opacity-88');
})->with([0, 1, 2, 3, 4, 5]);

it('tilts each polaroid and its tape by the promo\'s id', function () {
    $tilts = [
        ['-rotate-[2.2deg]', '-rotate-4'],
        ['rotate-[1.8deg]', 'rotate-3'],
        ['-rotate-[1.2deg]', '-rotate-2'],
        ['rotate-[2.4deg]', 'rotate-5'],
        ['-rotate-[1.8deg]', '-rotate-3'],
        ['rotate-[1.2deg]', 'rotate-2'],
    ];
    $ids = $this->user->promos()->active()->orderBy('valid_until')->orderBy('id')->pluck('id');

    $frames = boardFrames(Livewire::test(PromoBoard::class));

    expect($frames)->toHaveCount(6);

    foreach ($frames as $index => $frame) {
        [$tilt, $tapeTilt] = $tilts[$ids[$index] % 6];

        expect($frame->classList->contains($tilt))->toBeTrue()
            ->and($frame->firstElementChild->classList->contains($tapeTilt))->toBeTrue();
    }
});

it('makes each polaroid a button with its name and promo id', function () {
    $coffee = boardPromo($this->user, 'Coffee beans 1 kg');
    $frame = boardFrames(Livewire::test(PromoBoard::class))[1];

    expect($frame->tagName)->toBe('BUTTON')
        ->and($frame->getAttribute('aria-label'))->toBe('Coffee beans 1 kg, 2+1 free at Colruyt, until Sat, on list')
        ->and($frame->getAttribute('x-on:click'))->toBe("\$dispatch('show-promo', { id: {$coffee->id} })");
});

it('shows a promo\'s photo through the owner-checked route', function () {
    $coffee = boardPromo($this->user, 'Coffee beans 1 kg');
    $coffee->update(['photo_path' => 'promos/photo.jpg']);

    $images = boardHtml(Livewire::test(PromoBoard::class))->querySelectorAll('ul[role="list"] img');

    expect($images)->toHaveCount(1)
        ->and($images->item(0)->getAttribute('src'))->toBe(route('promos.photo', $coffee));
});

it('shows the on-list pill when the add-item sheet links an item', function () {
    $component = Livewire::test(PromoBoard::class);

    expect(boardPolaroids($component)[2][4])->toBeFalse();

    app(AddGroceryItem::class)->handle($this->user, 'Avocados', null, Store::Lidl, GroceryCategory::FruitAndVeg);

    $component->dispatch('grocery-item-added');

    expect(boardPolaroids($component)[2])->toBe(['Lidl', 'Avocados ×4', '€2.49', 'until Sat', true])
        ->and(trim(boardHtml($component)->querySelector('nav[aria-label="Groceries"] a')->textContent))->toBe('List · 7');
});

it('renders the board without a query per polaroid', function () {
    $queries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(PromoBoard::class);

        return count(DB::getQueryLog());
    };

    $before = $queries();

    foreach (['Rice', 'Flour', 'Sugar'] as $product) {
        $promo = $this->user->promos()->create(['store' => Store::Aldi, 'product' => $product, 'deal' => '€1', 'valid_until' => today()->addDays(3)]);
        GroceryItem::factory()->for($this->user)->create(['done' => false, 'promo_id' => $promo->id]);
    }

    expect(boardPolaroids(Livewire::test(PromoBoard::class)))->toHaveCount(9)
        ->and($queries())->toBe($before);
});
