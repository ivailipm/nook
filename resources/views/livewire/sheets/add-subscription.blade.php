@use('App\Enums\SubscriptionCategory')
@use('App\Enums\SubscriptionCycle')

{{-- Every close (the close button, Escape or the scrim) resets the form, so the sheet always opens empty with the date 30 days ahead. A dialog's close event doesn't bubble, hence the capture. --}}
<div x-on:close.capture="$wire.resetForm()">
    <x-sheet name="add-subscription" title="New subscription">
        <form wire:submit="save" class="flex flex-col gap-3.5">
            <div class="grid grid-cols-[minmax(0,1fr)_120px] gap-2.5">
                <x-field label="Name" name="subscriptionName" wire:model="subscriptionName" placeholder="Netflix" maxlength="255" />
                <x-field label="Price €" name="subscriptionPrice" wire:model="subscriptionPrice" inputmode="decimal" placeholder="9.99" class="font-mono" />
            </div>

            <div class="flex flex-col gap-1.5">
                <span id="add-subscription-billing" class="text-[13px] font-semibold">Billing</span>
                <div role="group" aria-labelledby="add-subscription-billing" class="flex flex-wrap gap-2">
                    @foreach (SubscriptionCycle::cases() as $option)
                        <x-chip :pressed="$cycle === $option->value" wire:click="$set('cycle', '{{ $option->value }}')">{{ Str::ucfirst($option->value) }}</x-chip>
                    @endforeach
                </div>
                @error('cycle')
                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <span id="add-subscription-category" class="text-[13px] font-semibold">Category</span>
                <div role="group" aria-labelledby="add-subscription-category" class="flex flex-wrap gap-2">
                    @foreach (SubscriptionCategory::cases() as $option)
                        <x-chip :pressed="$category === $option->value" wire:click="$set('category', '{{ $option->value }}')">{{ $option->value }}</x-chip>
                    @endforeach
                </div>
                @error('category')
                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

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
