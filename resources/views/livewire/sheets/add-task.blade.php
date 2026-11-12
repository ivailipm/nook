{{-- Every close (the close button, Escape, the scrim or a save) resets the form, so the sheet always opens empty and due today. A dialog's close event doesn't bubble, hence the capture. Opening refreshes the sheet, so the weekday chip is current on a page left open overnight. A tag added in the Your tags sheet on top of this one gets picked. --}}
<div
    x-on:close.capture="$wire.resetForm()"
    x-on:tag-added.window="$el.querySelector('dialog').open && $wire.set('tag', $event.detail.name)"
    x-on:open-sheet.window="$event.detail.name === 'add-task' && $wire.$refresh()"
>
    <x-sheet name="add-task" title="New task">
        <form wire:submit="save" class="flex flex-col gap-3.5">
            <x-field label="Task" name="taskTitle" wire:model="taskTitle" placeholder="Send CV to Cegeka" maxlength="255" />

            <div class="flex flex-col gap-1.5">
                <span id="add-task-due" class="text-[13px] font-semibold">Due</span>
                <div role="group" aria-labelledby="add-task-due" class="flex flex-wrap gap-2">
                    @foreach (['today' => 'Today', 'tomorrow' => 'Tomorrow', 'weekday' => today()->addDays(3)->format('D'), 'next-week' => 'Next week', 'none' => 'No date'] as $value => $label)
                        <x-chip :pressed="$due === $value" wire:click="$set('due', '{{ $value }}')">{{ $label }}</x-chip>
                    @endforeach
                </div>
                @error('due')
                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <x-task-chips prefix="add-task" :priority="$priority" :tag="$tag" :tags="$tags" :repeat="$repeat" />

            <button type="submit" class="min-h-12 cursor-pointer rounded-button bg-apricot text-[15px] font-bold text-on-apricot">Add task</button>
        </form>
    </x-sheet>
</div>
