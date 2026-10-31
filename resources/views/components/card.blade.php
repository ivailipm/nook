@props(['title' => null])

{{-- Callers separate the body's rows with `border-t border-hairline`. --}}
<section {{ $attributes->class('rounded-card border border-card-border bg-card-bg p-4') }}>
    @if ($title)
        <div class="mb-1.5 flex items-baseline justify-between">
            <h2 class="font-display text-[21px] font-semibold">{{ $title }}</h2>
            @isset($aside)
                <div class="text-[12px] text-cream-muted">{{ $aside }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
