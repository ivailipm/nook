<?php

namespace App\Actions;

use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Models\GroceryItem;
use App\Models\Promo;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Adds an open item to the user's grocery list, linked to a matching active promo and moved to that promo's store.
 * The quick add and the add-item sheet both add through here.
 */
class AddGroceryItem
{
    public function handle(User $user, string $name, ?string $qty, Store $store, GroceryCategory $category): GroceryItem
    {
        $promo = $this->activePromoFor($user, $name, $store);

        $item = $user->groceryItems()->create([
            'name' => Str::ucfirst($name),
            'qty' => blank($qty) ? null : $qty,
            'store' => $promo->store ?? $store,
            'category' => $category,
            'done' => false,
            'promo_id' => $promo?->id,
        ]);

        return $item->setRelation('promo', $promo);
    }

    /**
     * The toast after adding the item: the deal it was linked to, or just its name.
     */
    public function message(GroceryItem $item): string
    {
        return $item->promo === null
            ? "Added {$item->name}"
            : "Added, and linked to the {$item->promo->store->value} deal: {$item->promo->deal}";
    }

    /**
     * The user's oldest active promo whose product contains the name's first word (3 characters or more),
     * preferring one at the given store.
     */
    private function activePromoFor(User $user, string $name, Store $store): ?Promo
    {
        $word = Str::lower(Str::before($name, ' '));

        if (Str::length($word) < 3) {
            return null;
        }

        $promos = $user->promos()->active()->whereNotNull('product')->orderBy('id')->get()
            ->filter(fn (Promo $promo) => Str::contains($promo->product, $word, ignoreCase: true));

        return $promos->first(fn (Promo $promo) => $promo->store === $store) ?? $promos->first();
    }
}
