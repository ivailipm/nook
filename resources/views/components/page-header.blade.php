@props(['eyebrow', 'subtitle' => null])

{{-- The slot is the title; wrap its accent part in <em>. --}}
<div class="flex flex-col gap-0.5">
    <span class="text-[11px] font-bold tracking-[0.14em] text-cream-muted">{{ $eyebrow }}</span>
    <h1 class="font-display text-[34px] leading-[1.05] font-semibold tracking-[-0.015em] wide:text-[46px] [&_em]:font-medium [&_em]:text-accent">{{ $slot }}</h1>
    @if ($subtitle)
        <span class="font-script text-[20px] text-cream/80">{{ $subtitle }}</span>
    @endif
</div>
