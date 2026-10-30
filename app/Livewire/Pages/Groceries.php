<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Groceries')]
class Groceries extends Component
{
    public function render(): View
    {
        return view('livewire.pages.groceries');
    }
}
