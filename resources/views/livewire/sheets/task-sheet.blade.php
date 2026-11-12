{{-- A close (the close button, Escape, the scrim, saving or deleting) forgets the task and any unsaved change. A dialog's close event doesn't bubble, hence the capture. The ids differ from the add sheet's, because both sheets are on every page. A tag added in the Your tags sheet on top of this one gets picked. --}}
<div
    x-on:close.capture="$wire.clear()"
    x-on:tag-added.window="$el.querySelector('dialog').open && $wire.set('tag', $event.detail.name)"
>
    <x-sheet name="task" title="Edit task">
        @if ($task)
            {{-- The Due chips set a date, so one is pressed only when the date matches it: an overdue date or one further out presses none. --}}
            @php
                $dueChips = [
                    today()->toDateString() => 'Today',
                    today()->addDay()->toDateString() => 'Tomorrow',
                    today()->addDays(3)->toDateString() => today()->addDays(3)->format('D'),
                    today()->addDays(7)->toDateString() => 'Next week',
                    '' => 'No date',
                ];
            @endphp

            <form wire:submit="save" class="flex flex-col gap-3.5">
                <x-field label="Task" name="editTaskTitle" wire:model="editTaskTitle" maxlength="255" />

                <div class="flex flex-col gap-1.5">
                    <span id="edit-task-due" class="text-[13px] font-semibold">Due</span>
                    <div role="group" aria-labelledby="edit-task-due" class="flex flex-wrap gap-2">
                        @foreach ($dueChips as $value => $label)
                            <x-chip :pressed="$editDueDate === $value" wire:click="$set('editDueDate', '{{ $value }}')">{{ $label }}</x-chip>
                        @endforeach
                    </div>
                </div>

                <x-field label="Or pick a date" name="editDueDate" type="date" wire:model.live="editDueDate" />

                <x-task-chips prefix="edit-task" :priority="$priority" :tag="$tag" :tags="$tags" :repeat="$repeat" />

                <button type="submit" class="min-h-12 cursor-pointer rounded-button bg-apricot text-[15px] font-bold text-on-apricot">Save changes</button>
                <button type="button" wire:click="deleteTask" class="min-h-[46px] cursor-pointer rounded-button border border-destructive-border text-[14px] font-bold text-destructive">Delete task</button>
            </form>
        @endif
    </x-sheet>
</div>
