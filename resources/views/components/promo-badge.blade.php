@props(['promo', 'done' => false])

{{-- The deal on a list row, opening the promo sheet: gold while the item is open, greyed once it's ticked. The store shows from 900px. The ::before reaches past the pill, so the hit area is at least 44px without moving the layout. A very long deal truncates, so it can't push the row's remove button away. --}}
<button
    type="button"
    aria-haspopup="dialog"
    aria-label="Show the {{ $promo->store->value }} deal: {{ $promo->deal }}"
    x-on:click="$dispatch('show-promo', { id: {{ $promo->id }} })"
    {{ $attributes->class([
        'relative inline-flex max-w-[60%] cursor-pointer items-center gap-[5px] rounded-full px-2.5 py-1 text-[12px] font-bold whitespace-nowrap before:absolute before:inset-x-0 before:-inset-y-2.5',
        'bg-accent text-on-accent' => ! $done,
        'bg-white/12 text-cream/80' => $done,
    ]) }}
>
    <x-lucide-tag class="size-[13px] flex-none stroke-[2.2]" aria-hidden="true" />
    <span class="min-w-0 truncate">{{ $promo->deal }}<span class="hidden wide:inline"> · {{ $promo->store->value }}</span></span>
</button>
