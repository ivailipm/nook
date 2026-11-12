<?php

namespace App\Actions;

use App\Enums\Store;
use App\Models\CustomStore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Removes a store of the user's own and builds its message. Its items and promos move to Other, so nothing drops off
 * the list or the board.
 */
class RemoveStore
{
    /**
     * @return array{int, int} How many items and how many promos moved to Other.
     */
    public function handle(User $user, CustomStore $store): array
    {
        return DB::transaction(function () use ($user, $store) {
            $moved = [
                $user->groceryItems()->where('store', $store->name)->update(['store' => Store::Other->value]),
                $user->promos()->where('store', $store->name)->update(['store' => Store::Other->value]),
            ];

            $store->delete();

            return $moved;
        });
    }

    /**
     * The message after removing: "Removed Carrefour", or with what moved, "Removed Carrefour · 2 items and 1 deal moved to Other".
     */
    public function message(string $name, int $items, int $deals): string
    {
        $moved = collect(['item' => $items, 'deal' => $deals])
            ->filter()
            ->map(fn (int $count, string $noun) => $count.' '.Str::plural($noun, $count))
            ->implode(' and ');

        return $moved === '' ? "Removed {$name}" : "Removed {$name} · {$moved} moved to Other";
    }
}
