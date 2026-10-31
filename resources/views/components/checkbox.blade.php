@props(['label', 'checked' => false, 'variant' => 'grocery'])

{{-- A 44px hit area around a 22px box. The fill follows the item type (gold for groceries, apricot for tasks), not the ground. --}}
<button
    type="button"
    aria-pressed="{{ $checked ? 'true' : 'false' }}"
    aria-label="{{ $label }}"
    {{ $attributes->class('grid size-11 flex-none cursor-pointer place-items-center') }}
>
    <span @class([
        'grid size-[22px] place-items-center border-2',
        'rounded-checkbox' => $variant === 'grocery',
        'rounded-full' => $variant === 'task',
        'border-cream-faint' => ! $checked,
        'border-gold bg-gold text-on-gold' => $checked && $variant === 'grocery',
        'border-apricot bg-apricot text-on-apricot' => $checked && $variant === 'task',
    ])>
        @if ($checked)
            <x-lucide-check :class="$variant === 'task' ? 'size-3.5 stroke-[3.5]' : 'size-[15px] stroke-[3.5]'" aria-hidden="true" />
        @endif
    </span>
</button>
