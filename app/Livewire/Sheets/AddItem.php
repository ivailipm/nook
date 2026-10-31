<?php

namespace App\Livewire\Sheets;

use App\Actions\AddGroceryItem;
use App\Enums\GroceryCategory;
use App\Enums\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The "Add to your list" sheet, rendered once in the layout and opened by the Groceries FAB and the chooser.
 */
class AddItem extends Component
{
    public string $itemName = '';

    public string $itemQty = '';

    public string $store = Store::Lidl->value;

    public string $category = GroceryCategory::Pantry->value;

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'itemName' => ['required', 'string', 'max:255'],
            'itemQty' => ['string', 'max:255'],
            'store' => ['required', Rule::enum(Store::class)],
            'category' => ['required', Rule::enum(GroceryCategory::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['itemName' => 'item', 'itemQty' => 'qty'];
    }

    public function save(AddGroceryItem $addGroceryItem): void
    {
        $this->itemName = Str::trim($this->itemName);
        $this->itemQty = Str::trim($this->itemQty);

        $this->validate();

        $item = $addGroceryItem->handle(
            auth()->user(),
            $this->itemName,
            $this->itemQty,
            Store::from($this->store),
            GroceryCategory::from($this->category),
        );

        $this->resetForm();

        $this->dispatch('toast', message: "Added {$item->name}");
        $this->dispatch('grocery-item-added');
        $this->dispatch('close-sheet', name: 'add-item');
    }

    /**
     * Back to an empty item at Lidl and Pantry, without errors. The view calls this whenever the sheet closes.
     */
    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.sheets.add-item');
    }
}
