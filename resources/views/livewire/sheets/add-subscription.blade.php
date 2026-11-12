{{-- Every close (the close button, Escape or the scrim) resets the form, so the sheet always opens empty. A dialog's close event doesn't bubble, hence the capture. Opening resets the form too, so the date is 30 days from today on a page left open overnight (the layout keeps the sheet across navigations). --}}
<div
    x-on:close.capture="$wire.resetForm()"
    x-on:open-sheet.window="$event.detail.name === 'add-subscription' && $wire.resetForm()"
>
    <x-sheet name="add-subscription" title="New subscription">
        <form wire:submit="save" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-[minmax(0,1fr)_120px] gap-2.5">
                <x-field label="Name" name="subscriptionName" wire:model="subscriptionName" placeholder="Netflix" maxlength="255" />
                <x-field label="Price €" name="subscriptionPrice" wire:model="subscriptionPrice" inputmode="decimal" placeholder="9.99" class="font-mono" />
            </div>

            <x-subscription-chips prefix="add-subscription" :cycle="$cycle" :category="$category" />

            <div class="grid grid-cols-2 items-end gap-2.5">
                <x-field :label="$isTrial ? 'Trial ends' : 'Next payment'" name="nextPaymentDate" type="date" wire:model="nextPaymentDate" />
                <button
                    type="button"
                    wire:click="$toggle('isTrial')"
                    aria-pressed="{{ $isTrial ? 'true' : 'false' }}"
                    @class([
                        'min-h-[46px] cursor-pointer rounded-[12px] border text-[13px] font-bold',
                        'border-peach bg-peach text-on-peach' => $isTrial,
                        'border-white/16 bg-black/28 text-cream' => ! $isTrial,
                    ])
                >{{ $isTrial ? 'Free trial · on' : 'Free trial?' }}</button>
            </div>

            <p class="text-[12px] text-cream-muted">Free trials get a reminder 3 days and 1 day before they turn paid.</p>

            <button type="submit" class="min-h-12 cursor-pointer rounded-button bg-peach text-[15px] font-bold text-on-peach">Save subscription</button>
        </form>
    </x-sheet>
</div>
