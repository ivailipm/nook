<?php

namespace App\Actions;

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Adds an open item to the user's grocery list. The quick add and the add-item sheet both add through here.
 */
class AddGroceryItem
{
    public function handle(User $user, string $name, ?string $qty, Store $store, GroceryCategory $category): GroceryItem
    {
        return $user->groceryItems()->create([
            'name' => Str::ucfirst($name),
            'qty' => blank($qty) ? null : $qty,
            'store' => $store,
            'category' => $category,
            'done' => false,
            'promo_id' => null,
        ]);
    }
}
