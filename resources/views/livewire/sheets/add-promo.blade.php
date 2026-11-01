@use('App\Enums\Store')

{{-- Every close (the close button, Escape or the scrim) stops an upload still under way, deletes a finished one and resets the form. A dialog's close event doesn't bubble, hence the capture. Opening refreshes the sheet, so the link chips follow the list. --}}
<div
    x-on:close.capture="$wire.$cancelUpload('photo'); $wire.resetForm()"
    x-on:open-sheet.window="$event.detail.name === 'add-promo' && $wire.$refresh()"
>
    <x-sheet name="add-promo" title="Pin a deal">
        <form wire:submit="save" class="flex flex-col gap-3.5">
            <div class="flex flex-col gap-1.5">
                {{-- The label takes the focus ring while its hidden input is focused. --}}
                <label class="relative flex min-h-[120px] cursor-pointer flex-col items-center justify-center gap-1.5 overflow-hidden rounded-2xl border-2 border-dashed border-cream/45 bg-black/20 px-3.5 py-[18px] text-center has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-peach">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="Selected deal photo" class="max-h-40 max-w-full rounded-lg">
                    @else
                        <x-lucide-camera class="size-7 stroke-[1.8] text-gold" aria-hidden="true" />
                    @endif
                    <span wire:loading.remove wire:target="photo" class="text-[14px] font-bold">{{ $photo ? 'Tap to change the photo' : 'Upload a photo or screenshot' }}</span>
                    <span wire:loading wire:target="photo" class="text-[14px] font-bold">Uploading…</span>
                    <span class="text-[12px] text-cream/75">On Android you can also Share a screenshot straight to nook.</span>
                    <input
                        type="file"
                        accept="image/*"
                        wire:model="photo"
                        @error('photo') aria-invalid="true" aria-describedby="photo-error" @enderror
                        class="sr-only"
                    >
                </label>
                @error('photo')
                    <p id="photo-error" class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <button type="button" disabled class="inline-flex min-h-[34px] items-center gap-1.5 self-start rounded-full border border-dashed border-cream/45 px-3 text-[12px] font-semibold text-cream/80">
                <x-lucide-sparkles class="size-3.5 stroke-[1.8]" aria-hidden="true" />
                Read from photo · coming later
            </button>

            <div class="flex flex-col gap-1.5">
                <span id="add-promo-store" class="text-[13px] font-semibold">Store *</span>
                <div role="group" aria-labelledby="add-promo-store" class="flex flex-wrap gap-2">
                    @foreach (Store::cases() as $option)
                        <x-chip :pressed="$store === $option->value" wire:click="$set('store', '{{ $option->value }}')">{{ $option->value }}</x-chip>
                    @endforeach
                </div>
                @error('store')
                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <x-field label="Valid from" name="validFrom" type="date" wire:model="validFrom" />
                <x-field label="Valid until *" name="validUntil" type="date" gold wire:model="validUntil" />
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <x-field label="Product" name="product" wire:model="product" placeholder="Coffee beans 1kg" maxlength="255" />
                <x-field label="Deal or price" name="deal" wire:model="deal" placeholder="2+1 free · €3.49" maxlength="255" />
            </div>

            <x-field label="Note" name="note" wire:model="note" placeholder="€2.09 instead of €2.99" maxlength="255" />

            <div class="flex flex-col gap-1.5">
                <span id="add-promo-link" class="text-[13px] font-semibold">Link to a list item</span>
                <div role="group" aria-labelledby="add-promo-link" class="flex flex-wrap gap-2">
                    <x-chip :pressed="$linkItem === null" wire:click="$set('linkItem', null)">None</x-chip>
                    @foreach ($linkOptions as $option)
                        <x-chip :pressed="$linkItem === $option->id" wire:key="link-{{ $option->id }}" wire:click="$set('linkItem', {{ $option->id }})" class="max-w-full"><span class="block truncate">{{ $option->name }}</span></x-chip>
                    @endforeach
                </div>
                @error('linkItem')
                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="photo,save" class="min-h-12 cursor-pointer rounded-button bg-gold text-[15px] font-bold text-on-gold disabled:cursor-wait disabled:opacity-60">Pin to promo board</button>
        </form>
    </x-sheet>
</div>
