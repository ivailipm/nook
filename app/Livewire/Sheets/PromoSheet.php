<?php

namespace App\Livewire\Sheets;

use App\Actions\AddGroceryItem;
use App\Enums\GroceryCategory;
use App\Models\GroceryItem;
use App\Models\Promo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "The deal" sheet, rendered once in the layout and opened by the polaroids and the list badges.
 * Every action rereads the promo and rechecks its condition, so a double tap or a stale sheet does nothing.
 */
class PromoSheet extends Component
{
    #[Locked]
    public ?int $promoId = null;

    #[On('show-promo')]
    public function show(int $id): void
    {
        $this->promoId = auth()->user()->promos()->findOrFail($id)->id;

        $this->dispatch('open-sheet', name: 'promo');
    }

    public function gotIt(): void
    {
        $item = $this->openItem($this->promo());

        if ($item === null) {
            return;
        }

        $item->update(['done' => true]);

        $this->dispatch('toast', message: "{$item->name} ticked off");
        $this->finish();
    }

    public function unlink(): void
    {
        $item = $this->openItem($this->promo());

        if ($item === null) {
            return;
        }

        $item->update(['promo_id' => null]);

        $this->finish();
    }

    public function addToList(AddGroceryItem $addGroceryItem): void
    {
        $promo = $this->promo();

        if ($promo === null || $promo->daysLeft() < 0 || $this->openItem($promo) !== null) {
            return;
        }

        $item = $addGroceryItem->handle(auth()->user(), $promo->listItemName(), null, $promo->store, GroceryCategory::Pantry, $promo);

        $this->dispatch('toast', message: "{$item->name} added with the deal linked");
        $this->finish();
    }

    public function deletePromo(): void
    {
        $promo = $this->promo();

        if ($promo === null) {
            return;
        }

        $promo->delete();

        $this->dispatch('toast', message: 'Promo deleted');
        $this->finish();
    }

    /**
     * Forgets the promo. The view calls this whenever the sheet closes.
     */
    public function clear(): void
    {
        $this->reset('promoId');
    }

    public function render(): View
    {
        $promo = $this->promo();

        return view('livewire.sheets.promo-sheet', ['promo' => $promo, 'item' => $this->openItem($promo)]);
    }

    private function finish(): void
    {
        $this->clear();

        $this->dispatch('close-sheet', name: 'promo');
        $this->dispatch('promo-changed');
    }

    private function promo(): ?Promo
    {
        return $this->promoId === null ? null : auth()->user()->promos()->find($this->promoId);
    }

    /**
     * The user's first open item linked to the promo. A ticked item doesn't count.
     */
    private function openItem(?Promo $promo): ?GroceryItem
    {
        return $promo === null
            ? null
            : auth()->user()->groceryItems()->where('promo_id', $promo->id)->where('done', false)->orderBy('id')->first();
    }
}
