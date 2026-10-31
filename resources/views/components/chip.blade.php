@props(['pressed' => false, 'size' => 'md'])

{{-- A toggle pill: md (36px) for form choices, sm (34px) for filter rows. The ::before reaches 4px (md) or 5px (sm) past the pill, so the hit area is 44px without moving the layout; its insets start inside the 1px border. --}}
<button
    type="button"
    aria-pressed="{{ $pressed ? 'true' : 'false' }}"
    {{ $attributes->class([
        'relative cursor-pointer rounded-full border px-3.5 text-[13px] font-semibold before:absolute before:inset-x-0',
        'min-h-[34px] before:-inset-y-[6px]' => $size === 'sm',
        'min-h-9 before:-inset-y-[5px]' => $size === 'md',
        'border-cream bg-cream text-sheet-bg' => $pressed,
        'border-[rgb(255_255_255/0.16)] bg-[rgb(0_0_0/0.28)] text-cream' => ! $pressed,
    ]) }}
>{{ $slot }}</button>
