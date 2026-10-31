<?php

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\User;
use Database\Seeders\PromoSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds the local account, its grocery items and its promos once', function (string $environment) {
    $this->app['env'] = $environment;
    $this->freezeTime();

    $this->seed();
    $this->seed();

    $user = User::sole();

    expect($user->email)->toBe('ivayla@example.com')
        ->and($user->name)->toBe('Ivayla')
        ->and(Hash::check('password', $user->password))->toBeTrue();

    $promos = Promo::orderBy('id')->get();
    $day = fn (int $offset) => today()->addDays($offset)->toDateString();

    expect($promos->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($promos->map(fn (Promo $promo) => [
            $promo->store, $promo->product, $promo->deal, $promo->note,
            $promo->valid_from->toDateString(), $promo->valid_until->toDateString(), $promo->photo_path, $promo->tone,
        ])->all())->toBe([
            [Store::Colruyt, 'Coffee beans 1 kg', '2+1 free', '€11.98 for three', $day(-2), $day(2), null, 0],
            [Store::Delhaize, 'Dish soap', '−30%', '€2.09 instead of €2.99', $day(-5), $day(1), null, 1],
            [Store::Lidl, 'Greek yoghurt', '2nd ½ price', null, $day(-1), $day(5), null, 2],
            [Store::Aldi, 'Pasta 1 kg', '€1.79', 'instead of €2.39', $day(0), $day(6), null, 3],
            [Store::Colruyt, 'Laundry pods', '−25%', null, $day(-3), $day(12), null, 4],
            [Store::Lidl, 'Avocados ×4', '€2.49', null, $day(-1), $day(2), null, 5],
            [Store::Aldi, 'Mandarins 2 kg', '1+1', null, $day(-8), $day(-2), null, 3],
            [Store::Delhaize, 'Kitchen roll', '−40%', null, $day(-10), $day(-4), null, 1],
            [Store::Lidl, 'Gouda slices', '€1.99', null, $day(-7), $day(-1), null, 2],
        ]);

    $items = GroceryItem::orderBy('id')->get();

    expect($items->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($items->map(fn (GroceryItem $item) => [$item->name, $item->qty, $item->store, $item->category, $item->done, $item->promo_id])->all())->toBe([
            ['Coffee beans', '1kg', Store::Colruyt, GroceryCategory::Pantry, false, $promos[0]->id],
            ['Dish soap', null, Store::Delhaize, GroceryCategory::Household, false, $promos[1]->id],
            ['Oat milk', '2', Store::Aldi, GroceryCategory::Dairy, false, null],
            ['Spinach', '300g', Store::Lidl, GroceryCategory::FruitAndVeg, false, null],
            ['Greek yoghurt', '2', Store::Lidl, GroceryCategory::Dairy, false, $promos[2]->id],
            ['Bananas', '6', Store::Aldi, GroceryCategory::FruitAndVeg, true, null],
            ['Laundry pods', null, Store::Colruyt, GroceryCategory::Household, false, $promos[4]->id],
        ]);
})->with(['local', 'testing']);

it('seeds the promos for a user without the sample items, linking the items that exist', function () {
    $user = User::factory()->create();
    $coffee = GroceryItem::factory()->for($user)->create(['name' => 'Coffee beans']);

    app(PromoSeeder::class)->run($user);

    $promos = $user->promos()->orderBy('id')->get();

    expect($promos)->toHaveCount(9)
        ->and($coffee->fresh()->promo_id)->toBe($promos[0]->id);
});

it('seeds no user outside local and testing', function (string $environment) {
    $this->app['env'] = $environment;

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0)
        ->and(GroceryItem::count())->toBe(0)
        ->and(Promo::count())->toBe(0);
})->with(['production', 'staging']);
