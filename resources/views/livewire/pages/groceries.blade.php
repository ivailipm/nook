@use('App\Enums\Section')

<div class="flex flex-col gap-4.5 wide:gap-6">
    <x-page-header eyebrow="GROCERIES" :subtitle="$open === 0 ? 'list is empty. nice.' : $open.' '.Str::plural('thing', $open).' to grab'">Shopping <em>list</em></x-page-header>

    <x-groceries-switch :current="Section::Groceries" />

    {{-- The bar takes the focus ring while its input is focused. --}}
    <form wire:submit="add" class="flex items-center gap-2 rounded-input border border-input-border bg-black/30 py-[5px] pr-[5px] pl-4 has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-peach">
        <label for="quick-add-item" class="sr-only">Add an item</label>
        <input
            id="quick-add-item"
            wire:model="quickItem"
            placeholder="2 coffee @lidl #pantry"
            autocomplete="off"
            maxlength="255"
            @error('quickItem') aria-invalid="true" aria-describedby="quick-add-item-error" @enderror
            class="min-w-0 flex-1 bg-transparent py-2 text-[16px] focus-visible:outline-none"
        >
        <button type="submit" class="relative flex min-h-10 flex-none cursor-pointer items-center gap-1.5 rounded-button bg-accent px-3.5 text-[14px] font-bold text-on-accent before:absolute before:inset-x-0 before:-inset-y-0.5">
            <x-lucide-plus class="size-4 stroke-[2.4]" aria-hidden="true" />
            Add
        </button>
    </form>
    @error('quickItem')
        <p id="quick-add-item-error" class="-mt-2 text-[13px] text-destructive">{{ $message }}</p>
    @enderror
    <p class="-mt-2 text-[12px] text-cream-muted">Number = quantity · @store · #category. Items that match a saved deal get linked automatically.</p>

    <div role="group" aria-labelledby="group-by" class="flex flex-wrap items-center gap-2">
        <span id="group-by" class="mr-0.5 text-[12px] text-cream-muted">Group by</span>
        <x-chip size="sm" :pressed="! $byCategory" wire:click="$set('groupBy', 'store')">Store</x-chip>
        <x-chip size="sm" :pressed="$byCategory" wire:click="$set('groupBy', 'category')">Category</x-chip>
    </div>

    <div class="grid grid-cols-1 items-start gap-3.5 wide:grid-cols-[repeat(auto-fill,minmax(320px,1fr))]">
        @foreach ($groups as $group)
            <x-card :title="$group['title']" variant="group" wire:key="group-{{ $group['title'] }}">
                <x-slot:aside>{{ $group['left'] === 0 ? 'all done' : $group['left'].' left' }}</x-slot:aside>

                @foreach ($group['items'] as $item)
                    @php($detail = $byCategory ? $item->store->value : $item->category->value)
                    <div wire:key="item-{{ $item->id }}" class="flex min-h-[54px] items-center gap-2 border-t border-hairline">
                        {{-- Ticking moves the row, which drops focus, so a checkbox that had focus takes it back. --}}
                        <x-checkbox
                            variant="grocery"
                            :checked="$item->done"
                            :label="($item->done ? 'Untick ' : 'Tick off ').$item->name"
                            x-on:click="const focused = document.activeElement === $el; $wire.toggle({{ $item->id }}).then(() => focused && $el.focus())"
                            class="-ml-3"
                        />
                        <span class="flex min-w-0 flex-1 flex-col break-words">
                            <span @class(['text-[15px] font-semibold', 'line-through opacity-55' => $item->done])>{{ $item->name }}</span>
                            <span class="text-[12px] text-cream-muted">{{ $item->qty === null ? $detail : $item->qty.' · '.$detail }}</span>
                        </span>
                        <button
                            type="button"
                            wire:click="remove({{ $item->id }})"
                            aria-label="Remove {{ $item->name }}"
                            class="relative -mr-2 grid size-9 flex-none cursor-pointer place-items-center text-cream-faint before:absolute before:-inset-1"
                        >
                            <x-lucide-x class="size-4 stroke-2" aria-hidden="true" />
                        </button>
                    </div>
                @endforeach
            </x-card>
        @endforeach
    </div>

    @if ($ticked > 0)
        <button type="button" wire:click="clearTicked" class="relative min-h-10 cursor-pointer self-center rounded-full border border-white/16 bg-black/30 px-4.5 text-[13px] font-semibold text-cream before:absolute before:inset-x-0 before:-inset-y-[3px]">Clear {{ $ticked }} ticked</button>
    @endif
</div>
