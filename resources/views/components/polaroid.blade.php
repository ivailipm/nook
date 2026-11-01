@props(['promo', 'linked' => false])

@php
    // Full class names, so Tailwind finds them: the tone's photo area and tape, then the tilts, which follow the promo's id.
    [$photo, $tape] = [
        ['bg-tone-0-bg text-tone-0-text', 'bg-tone-0-tape'],
        ['bg-tone-1-bg text-tone-1-text', 'bg-tone-1-tape'],
        ['bg-tone-2-bg text-tone-2-text', 'bg-tone-2-tape'],
        ['bg-tone-3-bg text-tone-3-text', 'bg-tone-3-tape'],
        ['bg-tone-4-bg text-tone-4-text', 'bg-tone-4-tape'],
        ['bg-tone-5-bg text-tone-5-text', 'bg-tone-5-tape'],
    ][$promo->tone % 6];
    [$tilt, $tapeTilt] = [
        ['-rotate-[2.2deg]', '-rotate-4'],
        ['rotate-[1.8deg]', 'rotate-3'],
        ['-rotate-[1.2deg]', '-rotate-2'],
        ['rotate-[2.4deg]', 'rotate-5'],
        ['-rotate-[1.8deg]', '-rotate-3'],
        ['rotate-[1.2deg]', 'rotate-2'],
    ][$promo->id % 6];
    $daysLeft = $promo->daysLeft();
@endphp

{{-- A taped board polaroid: the store, product and deal on the tone's square, then the until label and, when an open item links to it, "on list". Expired promos fade to grey. --}}
<div {{ $attributes->class([
    'relative h-full rounded-polaroid bg-paper px-[9px] pt-[9px] pb-1.5 shadow-polaroid',
    $tilt,
    'opacity-55 grayscale' => $daysLeft < 0,
]) }}>
    <span @class(['absolute -top-[9px] left-1/2 -ml-[29px] h-[18px] w-[58px] opacity-88', $tape, $tapeTilt])></span>
    <div @class(['flex aspect-square flex-col gap-[5px] overflow-hidden p-[11px]', $photo])>
        <span class="text-[9px] font-bold tracking-[0.12em] uppercase">{{ $promo->store->value }}</span>
        @if ($promo->product !== null)
            <span class="font-display text-[16px] leading-[1.1] font-semibold">{{ $promo->product }}</span>
        @endif
        <span class="font-display text-[22px] leading-[1.05] font-bold">{{ $promo->deal }}</span>
    </div>
    <div class="flex min-h-7 items-center justify-between gap-1.5 pt-1">
        <span @class([
            'font-script text-[18px] font-bold',
            'text-[#6b625a]' => $daysLeft < 0,
            'text-ends-soon' => $daysLeft === 0 || $daysLeft === 1,
            'text-[#3a2a20]' => $daysLeft > 1,
        ])>{{ $promo->untilLabel() }}</span>
        @if ($linked)
            <span class="inline-flex items-center gap-[3px] rounded-full border border-on-list px-1.5 py-px text-[10px] font-bold text-on-list">
                <x-lucide-check class="size-2.5 stroke-[3.5]" aria-hidden="true" />
                on list
            </span>
        @endif
    </div>
</div>
