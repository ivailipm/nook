@use('App\Enums\Section')
@props(['current'])

<nav aria-label="Main" class="absolute inset-x-3.5 bottom-3.5 grid h-16 grid-cols-5 items-center rounded-[22px] border border-white/10 bg-[rgb(18_12_10/0.78)] shadow-nav backdrop-blur-[14px] wide:hidden">
    @foreach (Section::tabs() as $section)
        {{-- The FAB takes the centre column. --}}
        @if ($loop->index === intdiv($loop->count, 2))
            <div class="grid place-items-center">
                <button
                    type="button"
                    aria-label="{{ $current->fabLabel() }}"
                    @if ($current->fabSheet())
                        x-data
                        x-on:click="$dispatch('open-sheet', { name: @js($current->fabSheet()) })"
                        aria-haspopup="dialog"
                    @endif
                    class="-mt-7.5 grid size-[54px] cursor-pointer place-items-center rounded-full border-4 border-[rgb(18_12_10/0.9)] bg-peach text-on-peach shadow-fab"
                >
                    <x-lucide-plus class="size-[22px] stroke-[2.5]" aria-hidden="true" />
                </button>
            </div>
        @endif

        <a
            href="{{ route($section->value) }}"
            @if ($section === $current->tab()) aria-current="page" @endif
            @class([
                'flex h-full flex-col items-center justify-center gap-[3px] text-[10px]',
                'font-bold text-cream' => $section === $current->tab(),
                'font-medium text-cream/62' => $section !== $current->tab(),
            ])
        >
            <x-dynamic-component :component="'lucide-'.$section->icon()" class="size-5 stroke-[1.75]" aria-hidden="true" />
            {{ $section->tabLabel() }}
        </a>
    @endforeach
</nav>
