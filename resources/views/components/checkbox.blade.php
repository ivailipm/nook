@props(['label', 'checked' => false, 'variant' => 'grocery', 'size' => 'md'])

{{-- A 44px hit area around a 22px box (md) or the Home cards' 18px box (sm), whose 4px radius is the same for both variants. The fill follows the item type (gold for groceries, apricot for tasks), not the ground. --}}
<button
    type="button"
    aria-pressed="{{ $checked ? 'true' : 'false' }}"
    aria-label="{{ $label }}"
    {{ $attributes->class('grid size-11 flex-none cursor-pointer place-items-center') }}
>
    <span @class([
        'grid size-[22px] place-items-center border-2' => $size === 'md',
        'grid size-[18px] place-items-center rounded-[4px] border-[1.5px]' => $size === 'sm',
        'rounded-checkbox' => $size === 'md' && $variant === 'grocery',
        'rounded-full' => $size === 'md' && $variant === 'task',
        'border-cream-faint' => ! $checked,
        'border-gold bg-gold text-on-gold' => $checked && $variant === 'grocery',
        'border-apricot bg-apricot text-on-apricot' => $checked && $variant === 'task',
    ])>
        @if ($checked)
            <x-lucide-check :class="$size === 'sm' ? 'size-[13px] stroke-[3.5]' : ($variant === 'task' ? 'size-3.5 stroke-[3.5]' : 'size-[15px] stroke-[3.5]')" aria-hidden="true" />
        @endif
    </span>
</button>
