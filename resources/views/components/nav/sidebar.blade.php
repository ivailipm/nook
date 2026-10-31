@use('App\Enums\Section')
@props(['current'])

<nav aria-label="Main" class="hidden w-[236px] flex-none flex-col gap-1 border-r border-white/8 bg-black/28 px-4 py-7 wide:flex">
    <div class="px-3 pb-7 font-display text-[30px] leading-none font-semibold tracking-[-0.02em] italic">nook</div>

    @foreach (Section::cases() as $section)
        <a
            href="{{ route($section->value) }}"
            @if ($section === $current) aria-current="page" @endif
            @class([
                'flex min-h-11 items-center gap-3 rounded-button px-3 text-[15px]',
                'bg-cream/12 font-bold text-cream' => $section === $current,
                'font-medium text-cream-muted' => $section !== $current,
            ])
        >
            <x-dynamic-component :component="'lucide-'.$section->icon()" class="size-5 flex-none stroke-[1.75]" aria-hidden="true" />
            <span>{{ $section->label() }}</span>
        </a>
    @endforeach

    <div class="flex-1"></div>

    <form method="POST" action="{{ route('logout') }}" class="mb-3.5">
        @csrf
        <button type="submit" class="flex min-h-11 w-full cursor-pointer items-center gap-3 rounded-button px-3 text-[15px] font-medium text-cream-muted">
            <x-lucide-log-out class="size-5 flex-none stroke-[1.75]" aria-hidden="true" />
            <span>Log out</span>
        </button>
    </form>

    <button
        type="button"
        x-data
        x-on:click="$dispatch('open-sheet', { name: 'add-something' })"
        aria-haspopup="dialog"
        class="flex min-h-[46px] cursor-pointer items-center justify-center gap-2 rounded-full bg-peach text-[15px] font-bold text-on-peach"
    >
        <x-lucide-plus class="size-[18px] stroke-[2.25]" aria-hidden="true" />
        Add something
    </button>

    <div class="px-3 pt-3.5 font-script text-[18px] text-cream-muted">{{ now()->format('l · j F') }}</div>
</nav>
