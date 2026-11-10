@use('App\Enums\SubscriptionCategory')
@use('App\Enums\SubscriptionCycle')
@props(['prefix', 'cycle', 'category'])

{{-- The Billing and Category chip groups of both subscription sheets. Each chip sets the sheet's `cycle` or `category`; `prefix` keeps the label ids apart, because every sheet is on every page. --}}
<div class="flex flex-col gap-1.5">
    <span id="{{ $prefix }}-billing" class="text-[13px] font-semibold">Billing</span>
    <div role="group" aria-labelledby="{{ $prefix }}-billing" class="flex flex-wrap gap-2">
        @foreach (SubscriptionCycle::cases() as $option)
            <x-chip :pressed="$cycle === $option->value" wire:click="$set('cycle', '{{ $option->value }}')">{{ Str::ucfirst($option->value) }}</x-chip>
        @endforeach
    </div>
    @error('cycle')
        <p class="text-[13px] text-destructive">{{ $message }}</p>
    @enderror
</div>

<div class="flex flex-col gap-1.5">
    <span id="{{ $prefix }}-category" class="text-[13px] font-semibold">Category</span>
    <div role="group" aria-labelledby="{{ $prefix }}-category" class="flex flex-wrap gap-2">
        @foreach (SubscriptionCategory::cases() as $option)
            <x-chip :pressed="$category === $option->value" wire:click="$set('category', '{{ $option->value }}')">{{ $option->value }}</x-chip>
        @endforeach
    </div>
    @error('category')
        <p class="text-[13px] text-destructive">{{ $message }}</p>
    @enderror
</div>
