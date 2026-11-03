@props(['promo', 'done' => false, 'variant' => 'list'])

{{-- The deal on a list row, opening the promo sheet: gold while the item is open, greyed once it's ticked. The store shows from 900px. The home variant is Home's Groceries card: soft peach, smaller, and with the store at every width. The ::before reaches past the pill, so the hit area is at least 44px without moving the layout. A very long deal truncates, so it can't push the row's remove button away. --}}
<button
    type="button"
    aria-haspopup="dialog"
    aria-label="Show the {{ $promo->store->value }} deal: {{ $promo->deal }}"
    x-on:click="$dispatch('show-promo', { id: {{ $promo->id }} })"
    {{ $attributes->class([
        'relative inline-flex max-w-[60%] cursor-pointer items-center gap-[5px] rounded-full px-2.5 py-1 text-[12px] font-bold whitespace-nowrap before:absolute before:inset-x-0 before:-inset-y-2.5' => $variant === 'list',
        'bg-accent text-on-accent' => $variant === 'list' && ! $done,
        'bg-white/12 text-cream/80' => $variant === 'list' && $done,
        'relative inline-flex max-w-[60%] cursor-pointer items-center gap-[5px] rounded-full bg-[#f6d9c4] px-[9px] py-[3px] text-[11px] font-bold whitespace-nowrap text-[#4a1d10] before:absolute before:inset-x-0 before:-inset-y-3' => $variant === 'home',
    ]) }}
>
    <x-lucide-tag :class="$variant === 'home' ? 'size-3 flex-none stroke-[2.2]' : 'size-[13px] flex-none stroke-[2.2]'" aria-hidden="true" />
    <span class="min-w-0 truncate">{{ $promo->deal }}<span @class(['hidden wide:inline' => $variant === 'list'])> · {{ $promo->store->value }}</span></span>
</button>
