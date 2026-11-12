@props(['sheet', 'label', 'size' => 'md'])

{{-- The dashed + at the end of a chip row that a user can add to (the stores), opening that list's sheet on top of whatever is open. It is as tall as the chips beside it, md 36px or sm 34px, and the ::before, starting inside the 1px border, makes the hit area 44px square. --}}
<button
    type="button"
    aria-haspopup="dialog"
    aria-label="{{ $label }}"
    x-on:click="$dispatch('open-sheet', { name: @js($sheet) })"
    {{ $attributes->class([
        'relative grid flex-none cursor-pointer place-items-center rounded-full border border-dashed border-cream/45 text-cream/80 before:absolute',
        'size-[34px] before:-inset-[6px]' => $size === 'sm',
        'size-9 before:-inset-[5px]' => $size === 'md',
    ]) }}
>
    <x-lucide-plus class="size-4 stroke-[1.75]" aria-hidden="true" />
</button>
