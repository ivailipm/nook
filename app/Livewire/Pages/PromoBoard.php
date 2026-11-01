<?php

namespace App\Livewire\Pages;

use App\Enums\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Promo board')]
#[On('grocery-item-added')]
class PromoBoard extends Component
{
    /**
     * A store's value shows only that store; anything else shows every store.
     */
    public string $storeFilter = 'All';

    /**
     * Whether the page shows the archive (this week's expired promos) instead of the board.
     */
    public bool $archive = false;

    public function render(): View
    {
        $user = auth()->user();
        $store = Store::tryFrom($this->storeFilter);

        // The board runs soonest end first, the archive in the order the promos were added.
        $promos = $this->archive
            ? $user->promos()->expiredThisWeek()->orderBy('id')
            : $user->promos()->active()->orderBy('valid_until')->orderBy('id');

        if ($store !== null) {
            $promos->where('store', $store);
        }

        return view('livewire.pages.promo-board', [
            // A promo is "on list" while one of the user's open items links to it.
            'promos' => $promos->withExists(['groceryItems as linked' => fn (Builder $query) => $query->where('done', false)->where('user_id', $user->id)])->get(),
            'store' => $store,
            'open' => $user->groceryItems()->where('done', false)->count(),
            'expiredThisWeek' => $user->promos()->expiredThisWeek()->count(),
        ]);
    }
}
