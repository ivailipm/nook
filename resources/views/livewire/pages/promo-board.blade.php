@use('App\Enums\Section')
@use('Illuminate\Support\Js')

<div class="flex flex-col gap-4.5 wide:gap-6">
    <x-page-header eyebrow="GROCERIES" :subtitle="$archive ? 'expired deals rest here' : 'snapped from this week’s folders'">{{ $archive ? 'The' : 'Promo' }} <em>{{ $archive ? 'archive' : 'board' }}</em></x-page-header>

    <x-groceries-switch :current="Section::Promos" :list-count="$open" />

    <button
        type="button"
        aria-haspopup="dialog"
        x-on:click="$dispatch('open-sheet', { name: 'add-promo' })"
        class="flex w-full cursor-pointer items-center gap-3.5 rounded-2xl border border-white/12 bg-black/32 px-4 py-3.5 text-left"
    >
        <span class="grid size-10 flex-none place-items-center rounded-button bg-accent text-on-accent">
            <x-lucide-camera class="size-5 stroke-[1.9]" aria-hidden="true" />
        </span>
        <span class="flex flex-col">
            <span class="text-[15px] font-bold">Snap or share a deal</span>
            <span class="text-[12px] text-cream-muted">Screenshot a folder page, then Share → nook. Or upload it here.</span>
        </span>
    </button>

    {{-- The dashed + after the chips opens the Your stores sheet. --}}
    <div role="group" aria-label="Filter by store" class="flex flex-wrap gap-2">
        <x-chip size="sm" :pressed="$store === null" wire:click="$set('storeFilter', 'All')">All</x-chip>
        @foreach ($stores as $name)
            <x-chip size="sm" :pressed="$store === $name" wire:key="store-{{ $name }}" wire:click="$set('storeFilter', {{ Js::from($name) }})">{{ $name }}</x-chip>
        @endforeach
        <x-plus-chip sheet="stores" label="Add or remove stores" size="sm" />
    </div>

    @if ($promos->isEmpty())
        <p class="py-5 text-center font-script text-[21px] text-cream/78">nothing here yet. snap your first deal</p>
    @else
        <ul role="list" class="grid grid-cols-2 gap-x-4 gap-y-[26px] pt-2 wide:grid-cols-[repeat(auto-fill,minmax(180px,1fr))]">
            @foreach ($promos as $promo)
                <li wire:key="promo-{{ $promo->id }}">
                    <x-polaroid :promo="$promo" :linked="$promo->linked" />
                </li>
            @endforeach
        </ul>
    @endif

    {{-- The ::before reaches 3px past the 38px pill, so the hit area is 44px. No aria-pressed: the label says which view the button leads to. --}}
    <button type="button" wire:click="$toggle('archive')" class="relative inline-flex min-h-[38px] cursor-pointer items-center gap-2 self-center rounded-full border border-white/14 bg-black/32 px-4 text-[12px] font-semibold text-cream before:absolute before:inset-x-0 before:-inset-y-1">
        <x-lucide-archive class="size-3.5 stroke-2" aria-hidden="true" />
        {{ $archive ? '← Back to the board' : $expiredThisWeek.' expired this week · archive' }}
    </button>
</div>
