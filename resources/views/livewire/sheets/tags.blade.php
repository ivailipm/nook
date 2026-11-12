{{-- Every close (the close button, Escape or the scrim) clears the field, its error and the status. A dialog's close event doesn't bubble, hence the capture. It opens on top of the add-task or edit-task sheet when its + is tapped there. The status line says what the last add or remove did, because a toast would sit behind the open sheet; it stays in the page while empty, so a screen reader announces it. --}}
<div x-on:close.capture="$wire.resetForm()">
    <x-sheet name="tags" title="Your tags">
        <div class="flex flex-col gap-3.5">
            <form wire:submit="add" class="flex flex-col gap-3.5">
                <x-field label="New tag" name="tag" wire:model="tag" placeholder="gym" maxlength="20" autocomplete="off" autocapitalize="none" />
                <button type="submit" class="min-h-12 cursor-pointer rounded-button bg-apricot text-[15px] font-bold text-on-apricot">Add tag</button>
            </form>

            <div class="flex flex-col">
                <p role="status" class="mb-1 text-[13px] font-semibold empty:mb-0">{{ $status }}</p>
                <p class="text-[12px] text-cream-muted">{{ $builtIn }} are always there. One word per tag, so the quick add reads it as #gym.</p>
            </div>

            @if ($own->isNotEmpty())
                <div class="flex flex-col gap-1.5">
                    <span id="tags-own" class="text-[13px] font-semibold">Added by you</span>
                    <ul role="list" aria-labelledby="tags-own">
                        @foreach ($own as $custom)
                            <li wire:key="custom-tag-{{ $custom->id }}" class="flex min-h-12 items-center gap-2 border-t border-hairline">
                                <span class="min-w-0 flex-1 text-[15px] font-semibold break-words">{{ $custom->name }}</span>
                                <button
                                    type="button"
                                    wire:click="remove({{ $custom->id }})"
                                    aria-label="Remove the {{ $custom->name }} tag"
                                    class="relative -mr-2 grid size-9 flex-none cursor-pointer place-items-center text-cream-faint before:absolute before:-inset-1"
                                >
                                    <x-lucide-x class="size-4 stroke-2" aria-hidden="true" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-[12px] text-cream-muted">Removing a tag moves its tasks to home.</p>
                </div>
            @endif
        </div>
    </x-sheet>
</div>
