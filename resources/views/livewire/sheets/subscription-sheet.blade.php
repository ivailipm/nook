{{-- A close (the close button, Escape, the scrim, saving or deleting) forgets the subscription and any unsaved change. A dialog's close event doesn't bubble, hence the capture. The ids differ from the add sheet's, because both sheets are on every page. --}}
<div x-on:close.capture="$wire.clear()">
    <x-sheet name="subscription" title="Edit subscription">
        @if ($subscription)
            <form wire:submit="save" class="flex flex-col gap-3.5">
                <div class="grid grid-cols-[minmax(0,1fr)_120px] gap-2.5">
                    <x-field label="Name" name="editSubscriptionName" wire:model="editSubscriptionName" maxlength="255" />
                    <x-field label="Price €" name="editSubscriptionPrice" wire:model="editSubscriptionPrice" inputmode="decimal" class="font-mono" />
                </div>

                <x-subscription-chips prefix="edit-subscription" :cycle="$cycle" :category="$category" />

                <div class="grid grid-cols-2 items-end gap-2.5">
                    <x-field :label="$isTrial ? 'Trial ends' : 'Next payment'" name="editNextPaymentDate" type="date" wire:model="editNextPaymentDate" />
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

                <button type="submit" class="min-h-12 cursor-pointer rounded-button bg-peach text-[15px] font-bold text-on-peach">Save changes</button>
                <button type="button" wire:click="deleteSubscription" class="min-h-[46px] cursor-pointer rounded-button border border-destructive-border text-[14px] font-bold text-destructive">Delete subscription</button>
            </form>
        @endif
    </x-sheet>
</div>
