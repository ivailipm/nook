<?php

namespace App\Livewire\Pages;

use App\Actions\ToggleMorningNudge;
use App\Actions\ToggleTask;
use App\Support\HomeDashboard;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Home')]
#[On('grocery-item-added')]
#[On('task-added')]
#[On('promo-changed')]
class Home extends Component
{
    public function toggleTask(int $id, ToggleTask $toggleTask): void
    {
        $next = $toggleTask->handle(auth()->user()->tasks()->findOrFail($id));

        if ($next !== null) {
            $this->dispatch('toast', message: $toggleTask->message($next));
        }

        $this->dispatch('nav-counts-changed');
    }

    /**
     * Only ever ticks, so a double tap can't bring an item back. It can be unticked on the Groceries page.
     */
    public function tickItem(int $id): void
    {
        $item = auth()->user()->groceryItems()->findOrFail($id);

        if ($item->done) {
            return;
        }

        $item->update(['done' => true]);

        $this->dispatch('nav-counts-changed');
    }

    /**
     * Saves the setting only. Nothing sends the nudge yet.
     */
    public function toggleNudge(ToggleMorningNudge $toggle): void
    {
        $user = auth()->user();

        $toggle->handle($user);

        $this->dispatch('toast', message: $toggle->message($user));
    }

    /**
     * The task sheet saved or deleted a task, so the page re-renders. A saved task can move on the Today card or leave
     * it, so the row's button takes focus back by id once the page has changed, when the row is still there.
     */
    #[On('task-changed')]
    public function taskChanged(int $id): void
    {
        $this->js("document.getElementById('task-open-{$id}')?.focus()");
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.pages.home', [
            'user' => $user,
            'dashboard' => HomeDashboard::for($user),
            // Read once, so the date, the greeting and the line under it can't disagree at a boundary.
            'now' => now(),
        ]);
    }
}
