@props(['href', 'dot'])

{{-- A chip that navigates, so it has no aria-pressed. `dot` is a full class name, so Tailwind finds it. The ::before reaches 6px past the 34px pill, so the hit area is 44px without moving the layout; its insets start inside the 1px border. --}}
<a href="{{ $href }}" wire:navigate {{ $attributes->class('relative inline-flex min-h-[34px] items-center gap-2 rounded-full border border-white/14 bg-black/25 px-3.5 text-[13px] font-semibold text-cream before:absolute before:inset-x-0 before:-inset-y-[6px]') }}>
    <span aria-hidden="true" @class(['size-[7px] rounded-full', $dot])></span>
    {{ $slot }}
</a>
