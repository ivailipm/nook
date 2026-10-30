<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Promo board')]
class PromoBoard extends Component
{
    public function render(): View
    {
        return view('livewire.pages.promo-board');
    }
}
