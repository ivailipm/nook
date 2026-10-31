<?php

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('seeds the local account and its grocery items once', function (string $environment) {
    $this->app['env'] = $environment;

    $this->seed();
    $this->seed();

    $user = User::sole();

    expect($user->email)->toBe('ivayla@example.com')
        ->and($user->name)->toBe('Ivayla')
        ->and(Hash::check('password', $user->password))->toBeTrue();

    $items = GroceryItem::orderBy('id')->get();

    expect($items->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($items->map(fn (GroceryItem $item) => [$item->name, $item->qty, $item->store, $item->category, $item->done, $item->promo_id])->all())->toBe([
            ['Coffee beans', '1kg', Store::Colruyt, GroceryCategory::Pantry, false, null],
            ['Dish soap', null, Store::Delhaize, GroceryCategory::Household, false, null],
            ['Oat milk', '2', Store::Aldi, GroceryCategory::Dairy, false, null],
            ['Spinach', '300g', Store::Lidl, GroceryCategory::FruitAndVeg, false, null],
            ['Greek yoghurt', '2', Store::Lidl, GroceryCategory::Dairy, false, null],
            ['Bananas', '6', Store::Aldi, GroceryCategory::FruitAndVeg, true, null],
            ['Laundry pods', null, Store::Colruyt, GroceryCategory::Household, false, null],
        ]);
})->with(['local', 'testing']);

it('seeds no user outside local and testing', function (string $environment) {
    $this->app['env'] = $environment;

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0)
        ->and(GroceryItem::count())->toBe(0);
})->with(['production', 'staging']);
