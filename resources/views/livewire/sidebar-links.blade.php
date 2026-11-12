@use('App\Enums\Section')

<div class="flex flex-col gap-1">
    @foreach (Section::cases() as $section)
        <a
            href="{{ route($section->value) }}"
            wire:navigate
            @if ($section === $current) aria-current="page" @endif
            @class([
                'flex min-h-11 items-center gap-3 rounded-button px-3 text-[15px]',
                'bg-cream/12 font-bold text-cream' => $section === $current,
                'font-medium text-cream-muted' => $section !== $current,
            ])
        >
            <x-dynamic-component :component="'lucide-'.$section->icon()" class="size-5 flex-none stroke-[1.75]" aria-hidden="true" />
            <span class="flex-1">{{ $section->label() }}</span>
            @isset($counts[$section->value])
                <span class="font-mono text-[12px] opacity-80">{{ $counts[$section->value] }}</span>
            @endisset
        </a>
    @endforeach
</div>
