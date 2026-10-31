@props(['title' => null, 'variant' => 'default'])

{{-- Callers separate the body's rows with `border-t border-hairline`. The group variant is the Groceries group card: tighter padding and a 19px title. --}}
<section {{ $attributes->class([
    'rounded-card border border-card-border bg-card-bg',
    'p-4' => $variant === 'default',
    'px-4 pt-2 pb-1.5' => $variant === 'group',
]) }}>
    @if ($title)
        <div @class([
            'mb-1.5' => $variant === 'default',
            'flex items-baseline justify-between',
            'py-1.5' => $variant === 'group',
        ])>
            <h2 @class([
                'font-display',
                'text-[21px]' => $variant === 'default',
                'text-[19px]' => $variant === 'group',
                'font-semibold',
            ])>{{ $title }}</h2>
            @isset($aside)
                <div class="text-[12px] text-cream-muted">{{ $aside }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
