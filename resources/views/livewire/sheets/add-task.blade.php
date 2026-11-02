@use('App\Enums\TaskPriority')
@use('App\Enums\TaskTag')

{{-- Every close (the close button, Escape, the scrim or a save) resets the form, so the sheet always opens empty and due today. A dialog's close event doesn't bubble, hence the capture. Opening refreshes the sheet, so the weekday chip is current on a page left open overnight. --}}
<div
    x-on:close.capture="$wire.resetForm()"
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

            <div class="flex flex-col gap-1.5">
                <span id="add-task-priority" class="text-[13px] font-semibold">Priority</span>
                <div role="group" aria-labelledby="add-task-priority" class="flex flex-wrap gap-2">
                    @foreach (TaskPriority::cases() as $option)
                        <x-chip :pressed="$priority === $option->value" wire:click="$set('priority', '{{ $option->value }}')">{{ $option->label() }}</x-chip>
                    @endforeach
                    <x-chip :pressed="$priority === null" wire:click="$set('priority', null)">None</x-chip>
                </div>
                @error('priority')
                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <span id="add-task-tag" class="text-[13px] font-semibold">Tag</span>
                <div role="group" aria-labelledby="add-task-tag" class="flex flex-wrap gap-2">
                    @foreach (TaskTag::cases() as $option)
                        <x-chip :pressed="$tag === $option->value" wire:click="$set('tag', '{{ $option->value }}')">{{ $option->value }}</x-chip>
                    @endforeach
                </div>
                @error('tag')
                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <span id="add-task-repeat" class="text-[13px] font-semibold">Repeat</span>
                <div role="group" aria-labelledby="add-task-repeat" class="flex flex-wrap gap-2">
                    <x-chip :pressed="$repeat === 'once'" wire:click="$set('repeat', 'once')">Once</x-chip>
                    <x-chip :pressed="$repeat === 'weekly'" wire:click="$set('repeat', 'weekly')">Every week</x-chip>
                </div>
                @error('repeat')
                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="min-h-12 cursor-pointer rounded-button bg-apricot text-[15px] font-bold text-on-apricot">Add task</button>
        </form>
    </x-sheet>
</div>
