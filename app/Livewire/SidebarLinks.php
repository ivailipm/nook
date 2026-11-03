<?php

namespace App\Livewire;

use App\Enums\Section;
use App\Support\SidebarCounts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The sidebar's links with their counts. It re-renders during Livewire requests, so it's given its section
 * and never calls Section::current().
 */
#[On('nav-counts-changed')]
#[On('grocery-item-added')]
#[On('task-added')]
#[On('promo-changed')]
class SidebarLinks extends Component
{
    #[Locked]
    public Section $current;

    public function render(): View
    {
        return view('livewire.sidebar-links', ['counts' => SidebarCounts::for(auth()->user())]);
    }
}
