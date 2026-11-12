@use('App\Enums\TaskPriority')
@use('Illuminate\Support\Js')
@props(['prefix', 'priority', 'tag', 'tags', 'repeat'])

{{-- The Priority, Tag and Repeat chip groups of both task sheets. Each chip sets the sheet's `priority`, `tag` or `repeat`; `prefix` keeps the label ids apart, because every sheet is on every page. `tags` is the user's tags, and the Tag row ends in the + that opens the Your tags sheet. --}}
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
        @foreach ($tags as $option)
            <x-chip :pressed="$tag === $option" wire:key="{{ $prefix }}-tag-{{ $option }}" wire:click="$set('tag', {{ Js::from($option) }})">{{ $option }}</x-chip>
        @endforeach
        <x-plus-chip sheet="tags" label="Add or remove tags" />
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
