<?php

use App\Actions\AddGroceryItem;
use App\Enums\GroceryCategory;
use App\Livewire\Sheets\AddItem;
use App\Models\GroceryItem;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function addItemHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * Each chip row's label and its chips, with the pressed ones marked.
 *
 * @return array<string, list<string>>
 */
function addItemChips(Testable $component): array
{
    $page = addItemHtml($component);
    $rows = [];

    foreach ($page->querySelectorAll('[role="group"]') as $group) {
        $label = trim($page->getElementById($group->getAttribute('aria-labelledby'))->textContent);
        $rows[$label] = array_map(
            fn (Element $chip) => trim($chip->textContent).($chip->getAttribute('aria-pressed') === 'true' ? ' (pressed)' : ''),
            iterator_to_array($group->querySelectorAll('button[aria-pressed]')),
        );
    }

    return $rows;
}

/**
 * @return array<string, string>
 */
function addItemErrors(Testable $component): array
{
    $errors = [];

    foreach (addItemHtml($component)->querySelectorAll('input[aria-invalid="true"]') as $input) {
        $errors[$input->id] = trim(addItemHtml($component)->getElementById($input->getAttribute('aria-describedby'))->textContent);
    }

    return $errors;
}

dataset('default chips', ['Lidl and Pantry pressed' => [[
    'Store' => ['Aldi', 'Lidl (pressed)', 'Delhaize', 'Colruyt', 'Other'],
    'Category' => ['Fruit & veg', 'Dairy', 'Pantry (pressed)', 'Household'],
]]]);

it('renders the sheet with an empty item at Lidl and Pantry', function (array $chips) {
    $component = Livewire::test(AddItem::class);
    $page = addItemHtml($component);
    $dialog = $page->querySelector('dialog#sheet-add-item');
    $field = fn (string $label) => $page->getElementById(array_values(array_filter(
        iterator_to_array($page->querySelectorAll('label')),
        fn (Element $element) => trim($element->textContent) === $label,
    ))[0]->getAttribute('for'));
    $submit = $dialog->querySelector('button[type="submit"]');

    expect(trim($page->getElementById($dialog->getAttribute('aria-labelledby'))->textContent))->toBe('Add to your list')
        ->and($dialog->parentElement->getAttribute('x-on:close.capture'))->toBe('$wire.resetForm()')
        ->and($dialog->querySelector('form')->getAttribute('wire:submit'))->toBe('save')
        ->and([$field('Item')->id, $field('Item')->getAttribute('placeholder'), $field('Item')->getAttribute('maxlength'), $field('Item')->getAttribute('value')])
        ->toBe(['itemName', 'Coffee beans', '255', null])
        ->and([$field('Qty')->id, $field('Qty')->getAttribute('placeholder'), $field('Qty')->getAttribute('maxlength'), $field('Qty')->classList->contains('font-mono')])
        ->toBe(['itemQty', '1kg', '255', true])
        ->and(addItemChips($component))->toBe($chips)
        ->and(trim($submit->textContent))->toBe('Add to list')
        ->and($submit->classList->contains('bg-gold'))->toBeTrue()
        ->and($submit->classList->contains('text-on-gold'))->toBeTrue()
        ->and(addItemErrors($component))->toBe([]);

    $component->assertSet('itemName', '')
        ->assertSet('itemQty', '')
        ->assertSet('store', 'Lidl')
        ->assertSet('category', 'Pantry');
})->with('default chips');

it('presses the chip that was picked', function () {
    $component = Livewire::test(AddItem::class)->set('store', 'Aldi')->set('category', 'Fruit & veg');

    expect(addItemChips($component))->toBe([
        'Store' => ['Aldi (pressed)', 'Lidl', 'Delhaize', 'Colruyt', 'Other'],
        'Category' => ['Fruit & veg (pressed)', 'Dairy', 'Pantry', 'Household'],
    ]);
});

it('saves an item for the signed-in user, then closes and resets', function (array $chips) {
    $component = Livewire::test(AddItem::class)
        ->set('itemName', 'oat bars')
        ->set('itemQty', '4')
        ->set('store', 'Aldi')
        ->set('category', 'Pantry')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Added Oat bars')
        ->assertDispatched('grocery-item-added')
        ->assertDispatched('close-sheet', name: 'add-item')
        ->assertSet('itemName', '')
        ->assertSet('itemQty', '')
        ->assertSet('store', 'Lidl')
        ->assertSet('category', 'Pantry');

    $item = $this->user->groceryItems()->sole();

    expect([$item->name, $item->qty, $item->store, $item->category, $item->done, $item->promo_id])
        ->toBe(['Oat bars', '4', 'Aldi', GroceryCategory::Pantry, false, null])
        ->and(addItemChips($component))->toBe($chips);
})->with('default chips');

it('trims the fields and stores an empty qty as null', function (string $qty) {
    Livewire::test(AddItem::class)
        ->set('itemName', '  milk  ')
        ->set('itemQty', $qty)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Added Milk');

    $item = $this->user->groceryItems()->sole();

    expect([$item->name, $item->qty, $item->store, $item->category])->toBe(['Milk', null, 'Lidl', GroceryCategory::Pantry]);
})->with(['empty' => '', 'spaces' => '   ']);

it('requires a name and keeps the sheet open', function (string $name) {
    $component = Livewire::test(AddItem::class)
        ->set('itemName', $name)
        ->set('itemQty', '2')
        ->set('store', 'Colruyt')
        ->call('save')
        ->assertHasErrors(['itemName' => 'required'])
        ->assertNotDispatched('toast')
        ->assertNotDispatched('grocery-item-added')
        ->assertNotDispatched('close-sheet')
        ->assertSet('store', 'Colruyt');

    expect(GroceryItem::count())->toBe(0)
        ->and(addItemErrors($component))->toBe(['itemName' => 'The item field is required.']);
})->with(['empty' => '', 'spaces' => '   ']);

it('rejects a name or qty longer than 255 characters', function (string $field, string $label) {
    $component = Livewire::test(AddItem::class)
        ->set('itemName', 'Milk')
        ->set($field, str_repeat('a', 256))
        ->call('save')
        ->assertHasErrors([$field => 'max'])
        ->assertNotDispatched('close-sheet');

    expect(GroceryItem::count())->toBe(0)
        ->and(addItemErrors($component))->toBe([$field => "The {$label} field must not be greater than 255 characters."]);
})->with([
    'name' => ['itemName', 'item'],
    'qty' => ['itemQty', 'qty'],
]);

it('accepts a name and qty of 255 characters', function () {
    Livewire::test(AddItem::class)
        ->set('itemName', str_repeat('a', 255))
        ->set('itemQty', str_repeat('1', 255))
        ->call('save')
        ->assertHasNoErrors();

    $item = $this->user->groceryItems()->sole();

    expect(mb_strlen($item->name))->toBe(255)
        ->and(mb_strlen($item->qty))->toBe(255);
});

it('rejects a store or category outside the enums', function (string $field, string $value) {
    Livewire::test(AddItem::class)
        ->set('itemName', 'Milk')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors([$field])
        ->assertNotDispatched('close-sheet');

    expect(GroceryItem::count())->toBe(0);
})->with([
    'an unknown store' => ['store', 'Tesco'],
    'an empty store' => ['store', ''],
    'a store in the wrong case' => ['store', 'lidl'],
    'an unknown category' => ['category', 'Snacks'],
    'an empty category' => ['category', ''],
]);

it('resets the form and its errors when the sheet closes', function (array $chips) {
    $component = Livewire::test(AddItem::class)
        ->set('itemQty', '2')
        ->set('store', 'Colruyt')
        ->set('category', 'Dairy')
        ->call('save')
        ->assertHasErrors(['itemName'])
        ->call('resetForm')
        ->assertHasNoErrors()
        ->assertSet('itemName', '')
        ->assertSet('itemQty', '')
        ->assertSet('store', 'Lidl')
        ->assertSet('category', 'Pantry');

    expect(addItemChips($component))->toBe($chips)
        ->and(addItemErrors($component))->toBe([]);
})->with('default chips');

it('adds an open item through the user with a capitalised name', function () {
    GroceryItem::factory()->create();

    $item = app(AddGroceryItem::class)->handle($this->user, 'oat bars', '4', 'Aldi', GroceryCategory::Pantry);

    expect($item->exists)->toBeTrue()
        ->and($item->user->is($this->user))->toBeTrue()
        ->and($this->user->groceryItems()->sole()->is($item))->toBeTrue()
        ->and([$item->name, $item->qty, $item->store, $item->category, $item->done, $item->promo_id])
        ->toBe(['Oat bars', '4', 'Aldi', GroceryCategory::Pantry, false, null]);
});

it('adds an item with an empty qty as null', function (?string $qty) {
    $item = app(AddGroceryItem::class)->handle($this->user, 'Milk', $qty, 'Lidl', GroceryCategory::Dairy);

    expect($item->fresh()->qty)->toBeNull();
})->with(['null' => null, 'empty' => '']);
