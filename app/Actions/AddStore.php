<?php

namespace App\Actions;

use App\Models\CustomStore;
use App\Models\User;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds a store of the user's own, which then shows between Colruyt and Other wherever a store is picked or filtered,
 * and builds its message. The name is squished and capitalised. It can't repeat one of the user's stores in any case,
 * or be "All", the board's filter for every store.
 */
class AddStore
{
    /**
     * @throws ValidationException under the "store" key, the Your stores sheet's field.
     */
    public function handle(User $user, string $name): CustomStore
    {
        $name = Str::ucfirst(Str::squish($name));

        Validator::make(['store' => $name], [
            'store' => ['required', 'max:30', function (string $attribute, string $value, Closure $fail) use ($user) {
                if (Str::lower($value) === 'all') {
                    $fail('All is the filter for every store. Pick another name.');

                    return;
                }

                $existing = Arr::first($user->storeNames(), fn (string $store) => Str::lower($store) === Str::lower($value));

                if ($existing !== null) {
                    $fail("You already have {$existing}.");
                }
            }],
        ], [], ['store' => 'store name'])->validate();

        return $user->customStores()->create(['name' => $name]);
    }

    /**
     * The message after adding: "Added Carrefour to your stores".
     */
    public function message(CustomStore $store): string
    {
        return "Added {$store->name} to your stores";
    }
}
