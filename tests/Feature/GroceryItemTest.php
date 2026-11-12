<?php

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Models\GroceryItem;
use App\Models\User;

it('persists a factory item for a user', function () {
    $item = GroceryItem::factory()->create()->fresh();

    $this->assertModelExists($item);

    expect($item->user->is(User::sole()))->toBeTrue()
        ->and($item->store)->toBeIn(array_column(Store::cases(), 'value'))
        ->and($item->category)->toBeInstanceOf(GroceryCategory::class)
        ->and($item->done)->toBeFalse()
        ->and($item->promo_id)->toBeNull();
});

it('lists only the user\'s own items', function () {
    $user = User::factory()->create();
    $items = GroceryItem::factory()->count(2)->for($user)->create();
    GroceryItem::factory()->create();

    expect($user->groceryItems->modelKeys())->toEqualCanonicalizing($items->modelKeys());
});

it('deletes the user\'s items with the user', function () {
    $user = User::factory()->create();
    GroceryItem::factory()->for($user)->create();
    $other = GroceryItem::factory()->create();

    $user->delete();

    expect(GroceryItem::pluck('id')->all())->toBe([$other->id]);
});
