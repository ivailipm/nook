<?php

namespace Database\Seeders;

use App\Enums\GroceryCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroceryItemSeeder extends Seeder
{
    /**
     * Seed the prototype's sample grocery items for the user, unless they already have items.
     * The prototype links four of them to promos; PromoSeeder links them when it seeds the promos.
     */
    public function run(User $user): void
    {
        if ($user->groceryItems()->exists()) {
            return;
        }

        $user->groceryItems()->createMany([
            ['name' => 'Coffee beans', 'qty' => '1kg', 'store' => 'Colruyt', 'category' => GroceryCategory::Pantry, 'done' => false],
            ['name' => 'Dish soap', 'qty' => null, 'store' => 'Delhaize', 'category' => GroceryCategory::Household, 'done' => false],
            ['name' => 'Oat milk', 'qty' => '2', 'store' => 'Aldi', 'category' => GroceryCategory::Dairy, 'done' => false],
            ['name' => 'Spinach', 'qty' => '300g', 'store' => 'Lidl', 'category' => GroceryCategory::FruitAndVeg, 'done' => false],
            ['name' => 'Greek yoghurt', 'qty' => '2', 'store' => 'Lidl', 'category' => GroceryCategory::Dairy, 'done' => false],
            ['name' => 'Bananas', 'qty' => '6', 'store' => 'Aldi', 'category' => GroceryCategory::FruitAndVeg, 'done' => true],
            ['name' => 'Laundry pods', 'qty' => null, 'store' => 'Colruyt', 'category' => GroceryCategory::Household, 'done' => false],
        ]);
    }
}
