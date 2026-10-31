@props(['name', 'title'])

{{-- Opens on the window event open-sheet with { name }. The dialog has no padding, so a click on the dialog itself is on the scrim; a press that starts in the panel doesn't count. --}}
<dialog
    id="sheet-{{ $name }}"
    aria-labelledby="sheet-{{ $name }}-title"
    wire:ignore.self
    x-data="{ pressedScrim: false }"
    x-on:open-sheet.window="$event.detail.name === @js($name) && ! $el.open && $el.showModal()"
    x-on:pointerdown="pressedScrim = $event.target === $el"
    x-on:click="pressedScrim && $event.target === $el && $el.close()"
    class="mt-auto max-h-[88dvh] w-full max-w-none animate-sheet-up overflow-y-auto rounded-t-[24px] border border-white/10 bg-sheet-bg text-cream shadow-sheet backdrop:bg-scrim motion-reduce:animate-none wide:m-auto wide:w-[480px] wide:animate-none wide:rounded-[22px]"
>
    <div class="px-4.5 pt-2.5 pb-5.5">
        <div class="mx-auto mb-3 h-1 w-10 rounded-[2px] bg-cream/35" aria-hidden="true"></div>

        <div class="mb-3.5 flex items-center justify-between">
            <h2 id="sheet-{{ $name }}-title" class="font-display text-[22px] font-semibold">{{ $title }}</h2>
            <button
                type="button"
                aria-label="Close"
                x-on:click="$root.close()"
                class="relative grid size-9 flex-none cursor-pointer place-items-center rounded-full bg-white/8 text-cream before:absolute before:-inset-1"
            >
                <x-lucide-x class="size-4 stroke-2" aria-hidden="true" />
            </button>
        </div>

        {{ $slot }}
    </div>
</dialog>
