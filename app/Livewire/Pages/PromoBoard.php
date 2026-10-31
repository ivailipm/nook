<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Promo board')]
#[On('grocery-item-added')]
class PromoBoard extends Component
{
    public function render(): View
    {
        return view('livewire.pages.promo-board', [
            'open' => auth()->user()->groceryItems()->where('done', false)->count(),
        ]);
    }
}
