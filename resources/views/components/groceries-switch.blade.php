@use('App\Enums\Section')
@props(['current', 'listCount' => null, 'promoCount' => null])

{{-- The List | Promo board switch. Each link is a 36px pill whose ::before reaches 4px past it, so the hit area is 44px. "List" shows the open item count and "Promo board" the active promo count when one is given. --}}
<nav aria-label="Groceries" class="flex gap-1.5 self-start rounded-full bg-black/30 p-1">
    @foreach ([
        [Section::Groceries, $listCount === null ? 'List' : "List · {$listCount}"],
        [Section::Promos, $promoCount === null ? 'Promo board' : "Promo board · {$promoCount}"],
    ] as [$section, $label])
        <a
            href="{{ route($section->value) }}"
            wire:navigate
            @if ($section === $current) aria-current="page" @endif
            @class([
                'relative flex min-h-9 items-center rounded-full px-4 text-[13px] before:absolute before:inset-x-0 before:-inset-y-1',
                'bg-cream font-bold text-[#17301f]' => $section === $current,
                'font-semibold text-cream' => $section !== $current,
            ])
        >{{ $label }}</a>
    @endforeach
</nav>
