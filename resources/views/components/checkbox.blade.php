@props(['label', 'checkedLabel' => null, 'checked' => false, 'variant' => 'grocery', 'size' => 'md'])

{{-- A 44px hit area around a 22px box (md) or the Home cards' 18px box (sm), whose 4px radius is the same for both variants. The fill follows the item type (gold for groceries, apricot for tasks), not the ground. The box and its check mark follow the button's own aria-pressed, so resources/js/ticks.js can flip them before the server replies, swapping aria-label between data-label and data-checked-label. The check mark is always there, hidden while unpressed. --}}
<button
    type="button"
    aria-pressed="{{ $checked ? 'true' : 'false' }}"
    aria-label="{{ $checked ? ($checkedLabel ?? $label) : $label }}"
    data-label="{{ $label }}"
    data-checked-label="{{ $checkedLabel ?? $label }}"
    {{ $attributes->class('group/checkbox grid size-11 flex-none cursor-pointer place-items-center') }}
>
    <span @class([
        'grid size-[22px] place-items-center border-2' => $size === 'md',
        'grid size-[18px] place-items-center rounded-[4px] border-[1.5px]' => $size === 'sm',
        'rounded-checkbox' => $size === 'md' && $variant === 'grocery',
        'rounded-full' => $size === 'md' && $variant === 'task',
        'border-cream-faint',
        'group-aria-pressed/checkbox:border-gold group-aria-pressed/checkbox:bg-gold group-aria-pressed/checkbox:text-on-gold' => $variant === 'grocery',
        'group-aria-pressed/checkbox:border-apricot group-aria-pressed/checkbox:bg-apricot group-aria-pressed/checkbox:text-on-apricot' => $variant === 'task',
    ])>
        <x-lucide-check :class="($size === 'sm' ? 'size-[13px] stroke-[3.5]' : ($variant === 'task' ? 'size-3.5 stroke-[3.5]' : 'size-[15px] stroke-[3.5]')).' hidden group-aria-pressed/checkbox:block'" aria-hidden="true" />
    </span>
</button>
