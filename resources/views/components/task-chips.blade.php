@use('App\Enums\TaskPriority')
@use('App\Enums\TaskTag')
@props(['prefix', 'priority', 'tag', 'repeat'])

{{-- The Priority, Tag and Repeat chip groups of both task sheets. Each chip sets the sheet's `priority`, `tag` or `repeat`; `prefix` keeps the label ids apart, because every sheet is on every page. --}}
<div class="flex flex-col gap-1.5">
    <span id="{{ $prefix }}-priority" class="text-[13px] font-semibold">Priority</span>
    <div role="group" aria-labelledby="{{ $prefix }}-priority" class="flex flex-wrap gap-2">
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
    <span id="{{ $prefix }}-tag" class="text-[13px] font-semibold">Tag</span>
    <div role="group" aria-labelledby="{{ $prefix }}-tag" class="flex flex-wrap gap-2">
        @foreach (TaskTag::cases() as $option)
            <x-chip :pressed="$tag === $option->value" wire:click="$set('tag', '{{ $option->value }}')">{{ $option->value }}</x-chip>
        @endforeach
    </div>
    @error('tag')
        <p class="text-[13px] text-destructive">{{ $message }}</p>
    @enderror
</div>

<div class="flex flex-col gap-1.5">
    <span id="{{ $prefix }}-repeat" class="text-[13px] font-semibold">Repeat</span>
    <div role="group" aria-labelledby="{{ $prefix }}-repeat" class="flex flex-wrap gap-2">
        <x-chip :pressed="$repeat === 'once'" wire:click="$set('repeat', 'once')">Once</x-chip>
        <x-chip :pressed="$repeat === 'weekly'" wire:click="$set('repeat', 'weekly')">Every week</x-chip>
    </div>
    @error('repeat')
        <p class="text-[13px] text-destructive">{{ $message }}</p>
    @enderror
</div>
