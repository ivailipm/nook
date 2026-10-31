@props(['promo', 'done' => false])

{{-- The deal on a list row: gold while the item is open, greyed once it's ticked. The store shows from 900px. --}}
<span {{ $attributes->class([
    'inline-flex items-center gap-[5px] rounded-full px-2.5 py-1 text-[12px] font-bold whitespace-nowrap',
    'bg-accent text-on-accent' => ! $done,
    'bg-white/12 text-cream/80' => $done,
]) }}>
    <x-lucide-tag class="size-[13px] stroke-[2.2]" aria-hidden="true" />
    <span>{{ $promo->deal }}<span class="hidden wide:inline"> · {{ $promo->store->value }}</span></span>
</span>
