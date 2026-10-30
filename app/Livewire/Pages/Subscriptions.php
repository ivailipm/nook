<?php

namespace App\Livewire\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Subscriptions')]
class Subscriptions extends Component
{
    public function render(): View
    {
        return view('livewire.pages.subscriptions');
    }
}
