{{-- Every close (the close button, Escape or the scrim) clears the field, its error and the status. A dialog's close event doesn't bubble, hence the capture. It opens on top of the add-item or add-promo sheet when its + is tapped there. The status line says what the last add or remove did, because a toast would sit behind the open sheet; it stays in the page while empty, so a screen reader announces it. --}}
<div x-on:close.capture="$wire.resetForm()">
    <x-sheet name="stores" title="Your stores">
        <div class="flex flex-col gap-3.5">
            <form wire:submit="add" class="flex flex-col gap-3.5">
                <x-field label="New store" name="store" wire:model="store" placeholder="Carrefour" maxlength="30" autocomplete="off" />
                <button type="submit" class="min-h-12 cursor-pointer rounded-button bg-gold text-[15px] font-bold text-on-gold">Add store</button>
            </form>

            <div class="flex flex-col">
                <p role="status" class="mb-1 text-[13px] font-semibold empty:mb-0">{{ $status }}</p>
                <p class="text-[12px] text-cream-muted">{{ $builtIn }} are always there. Yours go between Colruyt and Other.</p>
            </div>

            @if ($own->isNotEmpty())
                <div class="flex flex-col gap-1.5">
                    <span id="stores-own" class="text-[13px] font-semibold">Added by you</span>
                    <ul role="list" aria-labelledby="stores-own">
                        @foreach ($own as $custom)
                            <li wire:key="custom-store-{{ $custom->id }}" class="flex min-h-12 items-center gap-2 border-t border-hairline">
                                <span class="min-w-0 flex-1 text-[15px] font-semibold break-words">{{ $custom->name }}</span>
                                <button
                                    type="button"
                                    wire:click="remove({{ $custom->id }})"
                                    aria-label="Remove {{ $custom->name }}"
                                    class="relative -mr-2 grid size-9 flex-none cursor-pointer place-items-center text-cream-faint before:absolute before:-inset-1"
                                >
                                    <x-lucide-x class="size-4 stroke-2" aria-hidden="true" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-[12px] text-cream-muted">Removing a store moves its items and deals to Other.</p>
                </div>
            @endif
        </div>
    </x-sheet>
</div>
