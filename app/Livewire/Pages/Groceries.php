<?php

namespace App\Livewire\Pages;

use App\Actions\AddGroceryItem;
use App\Enums\GroceryCategory;
use App\Enums\Store;
use App\Models\GroceryItem;
use App\Parsers\GroceryItemParser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Groceries')]
#[On('grocery-item-added')]
#[On('promo-changed')]
class Groceries extends Component
{
    public string $quickItem = '';

    /**
     * 'category' groups the list by category; anything else groups it by store.
     */
    public string $groupBy = 'store';

    public function add(GroceryItemParser $parser, AddGroceryItem $addGroceryItem): void
    {
        $this->validate(['quickItem' => ['string', 'max:255']], attributes: ['quickItem' => 'item']);

        $parsed = $parser->parse($this->quickItem);

        if ($parsed->name === '') {
            return;
        }

        $item = $addGroceryItem->handle(auth()->user(), $parsed->name, $parsed->qty, $parsed->store, $parsed->category);

        $this->reset('quickItem');

        $this->dispatch('toast', message: $addGroceryItem->message($item));
        $this->dispatch('nav-counts-changed');
    }

    /**
     * Sets the state that was tapped, never the opposite of the stored one, so a stale tab or a repeated save lands
     * the right way. An item already in that state isn't written.
     */
    public function toggle(int $id, bool $done): void
    {
        $item = auth()->user()->groceryItems()->findOrFail($id);

        $item->update(['done' => $done]);

        $this->dispatch('nav-counts-changed');
    }

    public function remove(int $id): void
    {
        auth()->user()->groceryItems()->findOrFail($id)->delete();

        $this->dispatch('nav-counts-changed');
    }

    public function clearTicked(): void
    {
        if (auth()->user()->groceryItems()->where('done', true)->delete() > 0) {
            $this->dispatch('nav-counts-changed');
        }
    }

    public function render(): View
    {
        $byCategory = $this->groupBy === 'category';

        // Open items first, then done ones, each in the order they were added.
        $items = auth()->user()->groceryItems()->with('promo')->orderBy('done')->orderBy('id')->get();
        $open = $items->where('done', false);

        $groups = collect($byCategory ? GroceryCategory::cases() : Store::cases())
            ->map(function (Store|GroceryCategory $key) use ($items, $byCategory) {
                $groupItems = $items->where($byCategory ? 'category' : 'store', $key);

                return ['title' => $key->value, 'items' => $groupItems, 'left' => $groupItems->where('done', false)->count()];
            })
            ->filter(fn (array $group) => $group['items']->isNotEmpty());

        return view('livewire.pages.groceries', [
            'groups' => $groups,
            'byCategory' => $byCategory,
            'open' => $open->count(),
            'deals' => $open->filter(fn (GroceryItem $item) => $item->hasActivePromo())->count(),
            'ticked' => $items->where('done', true)->count(),
            'activePromos' => auth()->user()->promos()->active()->count(),
        ]);
    }
}
