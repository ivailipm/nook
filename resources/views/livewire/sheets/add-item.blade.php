@use('App\Enums\GroceryCategory')
@use('Illuminate\Support\Js')

{{-- Every close (the close button, Escape, the scrim or a save) resets the form, so the sheet always opens empty at Lidl and Pantry. A dialog's close event doesn't bubble, hence the capture. A store added in the Your stores sheet on top of this one gets picked. --}}
<div
    x-on:close.capture="$wire.resetForm()"
    x-on:store-added.window="$el.querySelector('dialog').open && $wire.set('store', $event.detail.name)"
>
    <x-sheet name="add-item" title="Add to your list">
        <form wire:submit="save" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-[minmax(0,1fr)_96px] gap-2.5">
                <x-field label="Item" name="itemName" wire:model="itemName" placeholder="Coffee beans" maxlength="255" />
                <x-field label="Qty" name="itemQty" wire:model="itemQty" placeholder="1kg" maxlength="255" class="font-mono" />
            </div>

            <div class="flex flex-col gap-1.5">
                <span id="add-item-store" class="text-[13px] font-semibold">Store</span>
                <div role="group" aria-labelledby="add-item-store" class="flex flex-wrap gap-2">
                    @foreach ($stores as $option)
                        <x-chip :pressed="$store === $option" wire:key="store-{{ $option }}" wire:click="$set('store', {{ Js::from($option) }})">{{ $option }}</x-chip>
                    @endforeach
                    <x-plus-chip sheet="stores" label="Add or remove stores" />
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <span id="add-item-category" class="text-[13px] font-semibold">Category</span>
                <div role="group" aria-labelledby="add-item-category" class="flex flex-wrap gap-2">
                    @foreach (GroceryCategory::cases() as $option)
                        <x-chip :pressed="$category === $option->value" wire:click="$set('category', '{{ $option->value }}')">{{ $option->value }}</x-chip>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="min-h-12 cursor-pointer rounded-button bg-gold text-[15px] font-bold text-on-gold">Add to list</button>
        </form>
    </x-sheet>
</div>
