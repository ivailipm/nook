@props(['title' => null, 'variant' => 'default', 'titleClass' => ''])

{{-- Callers separate the body's rows with `border-t border-hairline`. The group variant is the Groceries group card: tighter padding and a 19px title. The money variant is the Subscriptions card: a stronger border and background, a 20px title, and no padding of its own because its three cards each pass their own. --}}
<section {{ $attributes->class([
    'rounded-card border',
    'border-card-border bg-card-bg' => $variant !== 'money',
    'border-white/12 bg-black/28' => $variant === 'money',
    'p-4' => $variant === 'default',
    'px-4 pt-2 pb-1.5' => $variant === 'group',
]) }}>
    @if ($title)
        <div @class([
            'mb-1.5' => $variant === 'default',
            'mb-3' => $variant === 'money',
            'flex items-baseline justify-between',
            'py-1.5' => $variant === 'group',
        ])>
            <h2 @class([
                'font-display',
                'text-[21px]' => $variant === 'default',
                'text-[19px]' => $variant === 'group',
                'text-[20px]' => $variant === 'money',
                'font-semibold',
                $titleClass => $titleClass !== '',
            ])>{{ $title }}</h2>
            @isset($aside)
                <div class="text-[12px] text-cream-muted">{{ $aside }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
