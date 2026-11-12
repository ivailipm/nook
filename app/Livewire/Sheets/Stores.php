<?php

namespace App\Livewire\Sheets;

use App\Actions\AddStore;
use App\Actions\RemoveStore;
use App\Enums\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Livewire\Component;

/**
 * The "Your stores" sheet, rendered once in the layout and opened by the dashed + after the store chips on the promo
 * board and in the add-item and add-promo sheets, on top of those sheets. It adds and removes the user's own stores.
 */
class Stores extends Component
{
    /**
     * The new store's name.
     */
    public string $store = '';

    /**
     * What the last add or remove did, shown in the sheet: the toast would sit behind it.
     */
    public string $status = '';

    /**
     * Adds the store and keeps the sheet open with an empty field, so several go in one after another.
     * An add-item or add-promo sheet open underneath picks the new store (store-added).
     */
    public function add(AddStore $addStore): void
    {
        $store = $addStore->handle(auth()->user(), $this->store);

        $this->resetForm();

        $this->status = $addStore->message($store);
        $this->dispatch('stores-changed');
        $this->dispatch('store-added', name: $store->name);
    }

    /**
     * Removes the store, moving its items and promos to Other. A store already gone (a double tap) is left alone.
     */
    public function remove(int $id, RemoveStore $removeStore): void
    {
        $store = auth()->user()->customStores()->find($id);

        if ($store === null) {
            return;
        }

        [$items, $deals] = $removeStore->handle(auth()->user(), $store);

        $this->status = $removeStore->message($store->name, $items, $deals);
        $this->dispatch('stores-changed');
    }

    /**
     * Back to an empty field without errors or a status. The view calls this whenever the sheet closes.
     */
    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.sheets.stores', [
            'own' => auth()->user()->customStores()->orderBy('id')->get(),
            'builtIn' => Arr::join(array_column(Store::cases(), 'value'), ', ', ' and '),
        ]);
    }
}
