<?php

use App\Enums\Store;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Creates a promo the way the add-promo form will, without a tone unless one is given.
 *
 * @param  array<string, mixed>  $attributes
 */
function newPromo(User $user, array $attributes = []): Promo
{
    return $user->promos()->create(['store' => Store::Lidl, 'product' => 'Gouda slices', 'deal' => '€1.99', 'valid_until' => today(), ...$attributes]);
}

it('persists an active factory promo for a user', function () {
    $promo = Promo::factory()->create()->fresh();

    $this->assertModelExists($promo);

    expect($promo->user)->toBeInstanceOf(User::class)
        ->and($promo->user->isNot($this->user))->toBeTrue()
        ->and($promo->user->promos->modelKeys())->toBe([$promo->id])
        ->and($promo->store)->toBeInstanceOf(Store::class)
        ->and($promo->valid_from->isBefore(today()))->toBeTrue()
        ->and($promo->valid_until->betweenIncluded(today(), today()->addDays(14)))->toBeTrue()
        ->and(Promo::active()->sole()->is($promo))->toBeTrue()
        ->and([$promo->note, $promo->photo_path])->toBe([null, null])
        ->and($promo->tone)->toBeInt()->toBeBetween(0, 5);
});

it('links grocery items to a promo', function () {
    $promo = Promo::factory()->for($this->user)->create();
    $item = GroceryItem::factory()->for($this->user)->create(['promo_id' => $promo->id]);
    GroceryItem::factory()->for($this->user)->create();

    expect($item->promo->is($promo))->toBeTrue()
        ->and($promo->groceryItems->modelKeys())->toBe([$item->id]);
});

it('gives a user\'s first promo the first tone', function () {
    expect(newPromo($this->user)->tone)->toBe(0);
});

it('gives a new promo the tone after the user\'s latest promo', function (int $latest, int $next) {
    Promo::factory()->for($this->user)->create(['tone' => 4]);
    Promo::factory()->for($this->user)->create(['tone' => $latest]);

    expect(newPromo($this->user)->tone)->toBe($next);
})->with([
    'after 2 comes 3' => [2, 3],
    'after 5 comes 0' => [5, 0],
]);

it('keeps a tone that is passed in', function (int $tone) {
    Promo::factory()->for($this->user)->create(['tone' => 2]);

    expect(newPromo($this->user, ['tone' => $tone])->fresh()->tone)->toBe($tone);
})->with([5, 0]);

it('ignores other users\' promos when picking the tone', function () {
    $other = User::factory()->create();
    Promo::factory()->for($other)->create(['tone' => 3]);

    expect(newPromo($this->user)->tone)->toBe(0);

    Promo::factory()->for($other)->create(['tone' => 4]);

    expect(newPromo($this->user)->tone)->toBe(1);
});

it('deletes the photo with the promo and unlinks its items', function () {
    Storage::fake('local');
    Storage::disk('local')->put('promos/gouda.jpg', 'photo');
    Storage::disk('local')->put('promos/other.jpg', 'photo');

    $promo = Promo::factory()->for($this->user)->create(['photo_path' => 'promos/gouda.jpg']);
    $item = GroceryItem::factory()->for($this->user)->create(['promo_id' => $promo->id]);

    $promo->delete();

    Storage::disk('local')->assertMissing('promos/gouda.jpg');
    Storage::disk('local')->assertExists('promos/other.jpg');

    expect($item->fresh()->promo_id)->toBeNull();
});

it('deletes a promo without a photo', function () {
    Storage::fake('local');
    Storage::disk('local')->put('promos/other.jpg', 'photo');

    $promo = Promo::factory()->for($this->user)->create();

    expect($promo->delete())->toBeTrue();

    $this->assertModelMissing($promo);
    Storage::disk('local')->assertExists('promos/other.jpg');
});

it('deletes the user\'s promos with the user', function () {
    Promo::factory()->for($this->user)->create();
    $other = Promo::factory()->create();

    $this->user->delete();

    expect(Promo::pluck('id')->all())->toBe([$other->id]);
});
