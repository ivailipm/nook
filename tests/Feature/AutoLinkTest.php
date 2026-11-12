<?php

use App\Actions\AddGroceryItem;
use App\Enums\GroceryCategory;
use App\Livewire\Pages\Groceries;
use App\Livewire\Sheets\AddItem;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function autoLinkAdd(User $user, string $name, string $store = 'Lidl'): GroceryItem
{
    return app(AddGroceryItem::class)->handle($user, $name, null, $store, GroceryCategory::Pantry);
}

/**
 * An active promo for the user.
 *
 * @param  array<string, mixed>  $attributes
 */
function autoLinkPromo(User $user, array $attributes): Promo
{
    return Promo::factory()->for($user)->create($attributes);
}

it('links an item to the active promo for its first word and moves it to the promo\'s store', function () {
    $promo = autoLinkPromo($this->user, ['store' => 'Colruyt', 'product' => 'Coffee beans 1 kg', 'deal' => '2+1 free']);

    $item = autoLinkAdd($this->user, 'coffee', 'Lidl');

    expect($item->relationLoaded('promo'))->toBeTrue()
        ->and($item->promo->is($promo))->toBeTrue()
        ->and([$item->fresh()->name, $item->fresh()->store, $item->fresh()->promo_id])->toBe(['Coffee', 'Colruyt', $promo->id])
        ->and(app(AddGroceryItem::class)->message($item))->toBe('Added, and linked to the Colruyt deal: 2+1 free');
});

it('prefers a promo at the item\'s store, then the oldest at any store', function (string $store, string $linked) {
    $promos = [
        'Aldi' => autoLinkPromo($this->user, ['store' => 'Aldi', 'product' => 'Greek yoghurt']),
        'Lidl' => autoLinkPromo($this->user, ['store' => 'Lidl', 'product' => 'Greek yoghurt']),
    ];

    $item = autoLinkAdd($this->user, 'greek yoghurt', $store);

    expect($item->promo_id)->toBe($promos[$linked]->id)
        ->and($item->store)->toBe($promos[$linked]->store);
})->with([
    'the same store' => ['Lidl', 'Lidl'],
    'another store' => ['Delhaize', 'Aldi'],
]);

it('matches the first word in any case and inside a longer word', function (string $name, string $product) {
    $promo = autoLinkPromo($this->user, ['product' => $product]);

    expect(autoLinkAdd($this->user, $name)->promo_id)->toBe($promo->id);
})->with([
    'upper case' => ['COFFEE', 'Coffee beans 1 kg'],
    'part of a word' => ['avocado', 'Avocados ×4'],
    'accented upper case' => ['ÉCLAIR', 'Éclairs ×6'],
]);

it('only matches on the first word', function () {
    autoLinkPromo($this->user, ['product' => 'Milk 1 l']);

    expect(autoLinkAdd($this->user, 'oat milk')->promo_id)->toBeNull();
});

it('needs a first word of at least 3 characters', function (string $name, bool $linked) {
    $promo = autoLinkPromo($this->user, ['product' => 'Tea bags']);

    expect(autoLinkAdd($this->user, $name)->promo_id)->toBe($linked ? $promo->id : null);
})->with([
    '2 characters' => ['te', false],
    '3 characters' => ['tea', true],
]);

it('links a promo through its last day and not after', function (string $validUntil, bool $linked) {
    $this->travelTo('2026-10-29 18:00');

    $promo = autoLinkPromo($this->user, ['product' => 'Gouda slices', 'valid_until' => $validUntil]);

    expect(autoLinkAdd($this->user, 'gouda')->promo_id)->toBe($linked ? $promo->id : null);
})->with([
    'ended yesterday' => ['2026-10-28', false],
    'ends today' => ['2026-10-29', true],
]);

it('ignores other users\' promos and promos without a product', function () {
    autoLinkPromo(User::factory()->create(), ['product' => 'Coffee beans 1 kg']);
    autoLinkPromo($this->user, ['product' => null, 'deal' => 'Coffee −20%']);

    expect(autoLinkAdd($this->user, 'coffee')->promo_id)->toBeNull();
});

it('keeps the given store and no promo when nothing matches', function () {
    autoLinkPromo($this->user, ['store' => 'Colruyt', 'product' => 'Coffee beans 1 kg']);

    $item = autoLinkAdd($this->user, 'spinach', 'Aldi');

    expect($item->relationLoaded('promo'))->toBeTrue()
        ->and($item->promo)->toBeNull()
        ->and([$item->fresh()->store, $item->fresh()->promo_id])->toBe(['Aldi', null])
        ->and(app(AddGroceryItem::class)->message($item))->toBe('Added Spinach');
});

dataset('add toasts', [
    'a linked item' => ['coffee', 'Added, and linked to the Colruyt deal: 2+1 free', 'Colruyt'],
    'an unlinked item' => ['spinach', 'Added Spinach', 'Lidl'],
]);

it('toasts the linked deal from the quick add', function (string $name, string $toast, string $store) {
    $this->actingAs($this->user);
    autoLinkPromo($this->user, ['store' => 'Colruyt', 'product' => 'Coffee beans 1 kg', 'deal' => '2+1 free']);

    Livewire::test(Groceries::class)
        ->set('quickItem', $name)
        ->call('add')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: $toast);

    expect($this->user->groceryItems()->sole()->store)->toBe($store);
})->with('add toasts');

it('toasts the linked deal from the add-item sheet', function (string $name, string $toast, string $store) {
    $this->actingAs($this->user);
    autoLinkPromo($this->user, ['store' => 'Colruyt', 'product' => 'Coffee beans 1 kg', 'deal' => '2+1 free']);

    Livewire::test(AddItem::class)
        ->set('itemName', $name)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: $toast);

    expect($this->user->groceryItems()->sole()->store)->toBe($store);
})->with('add toasts');
