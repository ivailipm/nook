@use('App\Support\PolaroidStyle')
@props(['promo', 'linked' => false])

@php
    // Full class names, so Tailwind finds them: the tilts, which follow the promo's id.
    [$tilt, $tapeTilt] = [
        ['-rotate-[2.2deg]', '-rotate-4'],
        ['rotate-[1.8deg]', 'rotate-3'],
        ['-rotate-[1.2deg]', '-rotate-2'],
        ['rotate-[2.4deg]', 'rotate-5'],
        ['-rotate-[1.8deg]', '-rotate-3'],
        ['rotate-[1.2deg]', 'rotate-2'],
    ][$promo->id % 6];
    $label = collect([$promo->product, "{$promo->deal} at {$promo->store->value}", $promo->untilLabel(), $linked ? 'on list' : null])->whereNotNull()->implode(', ');
@endphp

{{-- A taped board polaroid that opens the promo sheet: the store, product and deal on the photo or the tone's square, then the until label and, when an open item links to it, "on list". Expired promos fade to grey. It's a flex column because a button stretched to its row's height centres block content. --}}
<button
    type="button"
    aria-haspopup="dialog"
    aria-label="{{ $label }}"
    x-on:click="$dispatch('show-promo', { id: {{ $promo->id }} })"
    {{ $attributes->class([
        'relative flex h-full w-full cursor-pointer flex-col rounded-polaroid bg-paper px-[9px] pt-[9px] pb-1.5 text-left shadow-polaroid',
        $tilt,
        'opacity-55 grayscale' => $promo->daysLeft() < 0,
    ]) }}
>
    <span @class(['absolute -top-[9px] left-1/2 -ml-[29px] h-[18px] w-[58px] opacity-88', PolaroidStyle::tape($promo), $tapeTilt])></span>
    <span @class(['relative flex aspect-square flex-col gap-[5px] overflow-hidden p-[11px]', PolaroidStyle::photo($promo)])>
        @if ($promo->photoUrl() !== null)
            <img src="{{ $promo->photoUrl() }}" alt="Photo of the {{ $promo->store->value }} deal" loading="lazy" class="absolute inset-0 size-full object-cover">
        @endif
        <span class="relative text-[9px] font-bold tracking-[0.12em] uppercase">{{ $promo->store->value }}</span>
        @if ($promo->product !== null)
            <span class="relative font-display text-[16px] leading-[1.1] font-semibold">{{ $promo->product }}</span>
        @endif
        <span class="relative font-display text-[22px] leading-[1.05] font-bold">{{ $promo->deal }}</span>
    </span>
    <span class="flex min-h-7 items-center justify-between gap-1.5 pt-1">
        <span @class(['font-script text-[18px] font-bold', PolaroidStyle::untilColour($promo)])>{{ $promo->untilLabel() }}</span>
        @if ($linked)
            <span class="inline-flex items-center gap-[3px] rounded-full border border-on-list px-1.5 py-px text-[10px] font-bold text-on-list">
                <x-lucide-check class="size-2.5 stroke-[3.5]" aria-hidden="true" />
                on list
            </span>
        @endif
    </span>
</button>
