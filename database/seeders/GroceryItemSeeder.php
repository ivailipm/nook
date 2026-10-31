<?php

namespace Database\Seeders;

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroceryItemSeeder extends Seeder
{
    /**
     * Seed the prototype's sample grocery items for the user, unless they already have items.
     * The prototype links four of them to promos; M3 relinks them when it seeds the promos.
     */
    public function run(User $user): void
    {
        if ($user->groceryItems()->exists()) {
            return;
        }

        $user->groceryItems()->createMany([
            ['name' => 'Coffee beans', 'qty' => '1kg', 'store' => Store::Colruyt, 'category' => GroceryCategory::Pantry, 'done' => false],
            ['name' => 'Dish soap', 'qty' => null, 'store' => Store::Delhaize, 'category' => GroceryCategory::Household, 'done' => false],
            ['name' => 'Oat milk', 'qty' => '2', 'store' => Store::Aldi, 'category' => GroceryCategory::Dairy, 'done' => false],
            ['name' => 'Spinach', 'qty' => '300g', 'store' => Store::Lidl, 'category' => GroceryCategory::FruitAndVeg, 'done' => false],
            ['name' => 'Greek yoghurt', 'qty' => '2', 'store' => Store::Lidl, 'category' => GroceryCategory::Dairy, 'done' => false],
            ['name' => 'Bananas', 'qty' => '6', 'store' => Store::Aldi, 'category' => GroceryCategory::FruitAndVeg, 'done' => true],
            ['name' => 'Laundry pods', 'qty' => null, 'store' => Store::Colruyt, 'category' => GroceryCategory::Household, 'done' => false],
        ]);
    }
}
