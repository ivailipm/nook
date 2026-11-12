@use('App\Enums\TaskPriority')
@use('Illuminate\Support\Js')

<div class="flex flex-col gap-4.5 wide:gap-6">
    <x-page-header eyebrow="TO-DO" subtitle="one at a time is plenty">Small <em>things</em></x-page-header>

    {{-- The bar takes the focus ring while its input is focused. --}}
    <form wire:submit="add" class="flex items-center gap-2 rounded-input border border-input-border bg-black/30 py-[5px] pr-[5px] pl-4 has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-peach">
        <label for="quick-add-task" class="sr-only">Add a task</label>
        <input
            id="quick-add-task"
            wire:model="quickTask"
            placeholder="Email the prof tomorrow !high #school"
            autocomplete="off"
            maxlength="255"
            @error('quickTask') aria-invalid="true" aria-describedby="quick-add-task-error" @enderror
            class="min-w-0 flex-1 bg-transparent py-2 text-[16px] focus-visible:outline-none"
        >
        <button type="submit" class="relative flex min-h-10 flex-none cursor-pointer items-center gap-1.5 rounded-button bg-accent px-3.5 text-[14px] font-bold text-on-accent before:absolute before:inset-x-0 before:-inset-y-0.5">
            <x-lucide-plus class="size-4 stroke-[2.4]" aria-hidden="true" />
            Add
        </button>
    </form>
    @error('quickTask')
        <p id="quick-add-task-error" class="-mt-2 text-[13px] text-destructive">{{ $message }}</p>
    @enderror
    <p class="-mt-2 text-[12px] text-cream-muted">today / tomorrow / a weekday · !high !low · #school #home #internship · "every tue" repeats</p>

    <div role="group" aria-label="Filter by tag" class="flex flex-wrap gap-2">
        <x-chip size="sm" :pressed="$tag === null" wire:click="$set('tagFilter', 'All')">All</x-chip>
        @foreach ($tags as $name)
            <x-chip size="sm" :pressed="$tag === $name" wire:key="tag-{{ $name }}" wire:click="$set('tagFilter', {{ Js::from($name) }})">{{ $name }}</x-chip>
        @endforeach
        <x-plus-chip sheet="tags" label="Add or remove tags" size="sm" />
    </div>

    <div class="grid grid-cols-1 items-start gap-3.5 wide:grid-cols-2">
        <div class="flex flex-col gap-3.5">
            @forelse ($groups as $group)
                <x-card
                    variant="group"
                    :title="$group['title']"
                    :title-class="['Overdue' => 'text-overdue', 'Done' => 'text-cream-muted'][$group['title']] ?? ''"
                    wire:key="group-{{ $group['title'] }}"
                >
                    <x-slot:aside>{{ $group['tasks']->count() }} {{ Str::plural('task', $group['tasks']->count()) }}</x-slot:aside>

                    @foreach ($group['tasks'] as $task)
                        <div wire:key="task-{{ $task->id }}" @if ($task->done) data-done @endif class="flex min-h-14 items-start gap-2 border-t border-hairline py-1">
                            {{-- $tick flips the box and the struck-through title at once and saves the tapped state. The reply moves the row to another card, which rebuilds the checkbox, so a checkbox that had focus takes it back by id. --}}
                            <x-checkbox
                                variant="task"
                                id="task-check-{{ $task->id }}"
                                :checked="$task->done"
                                :label="'Complete: '.$task->title"
                                :checked-label="'Mark not done: '.$task->title"
                                x-on:click="const focused = document.activeElement === $el; $tick((done, $wire) => $wire.toggle({{ $task->id }}, done)).then(() => focused && document.getElementById('task-check-{{ $task->id }}')?.focus())"
                                class="-ml-3"
                            />
                            {{-- Opens the edit sheet. The checkbox stays its own button, so ticking never opens it. --}}
                            <button
                                type="button"
                                id="task-open-{{ $task->id }}"
                                aria-haspopup="dialog"
                                x-on:click="$dispatch('show-task', { id: {{ $task->id }} })"
                                class="flex min-w-0 flex-1 cursor-pointer flex-col gap-1 pt-2.5 text-left break-words"
                            >
                                <span class="text-[15px] font-semibold in-data-done:line-through in-data-done:opacity-55">{{ $task->title }}</span>
                                <span class="flex flex-wrap items-center gap-2 text-[12px] text-cream-muted">
                                    @if ($task->priority !== null)
                                        <span @class([
                                            'inline-flex items-center gap-1 font-bold',
                                            'text-overdue' => $task->priority === TaskPriority::High,
                                            'text-due-today' => $task->priority === TaskPriority::Med,
                                            'text-cream-muted' => $task->priority === TaskPriority::Low,
                                        ])>
                                            <span aria-hidden="true" @class([
                                                'size-2 border-[1.5px]',
                                                'rounded-[2px] border-overdue bg-overdue' => $task->priority === TaskPriority::High,
                                                'rounded-full border-due-today bg-due-today' => $task->priority === TaskPriority::Med,
                                                'rounded-full border-cream-muted' => $task->priority === TaskPriority::Low,
                                            ])></span>
                                            {{ $task->priority->label() }}
                                        </span>
                                    @endif
                                    <span @class([
                                        'font-semibold',
                                        'text-overdue' => $task->isOverdue(),
                                        'text-due-today' => ! $task->done && $task->daysUntilDue() === 0,
                                    ])>{{ $task->dueLabel() }}</span>
                                    @if ($task->repeatLabel() !== null)
                                        <span class="inline-flex items-center gap-1">
                                            <x-lucide-repeat class="size-[13px] stroke-2" aria-hidden="true" />
                                            {{ $task->repeatLabel() }}
                                        </span>
                                    @endif
                                    <span class="rounded-full bg-white/9 px-2 py-px text-cream">{{ $task->tag }}</span>
                                </span>
                            </button>
                        </div>
                    @endforeach
                </x-card>
            @empty
                <p class="py-5 text-center font-script text-[21px] text-cream/78">nothing here yet. add a small thing</p>
            @endforelse
        </div>

        <x-card class="flex items-center gap-3">
            <span class="grid size-10 flex-none place-items-center rounded-button bg-accent/16 text-accent">
                <x-lucide-bell class="size-5 stroke-[1.8]" aria-hidden="true" />
            </span>
            <div class="flex flex-1 flex-col">
                <span class="text-[14px] font-bold">Morning nudge</span>
                <span class="text-[12px] text-cream-muted">A push notification at 08:00 with what's due today.</span>
            </div>
            {{-- The ::before reaches 7px past the 30px track, so the hit area is 44px. The knob's side shows the state as well as the colour. --}}
            <button
                type="button"
                wire:click="toggleNudge"
                aria-pressed="{{ $nudge ? 'true' : 'false' }}"
                aria-label="Morning nudge"
                @class([
                    'relative flex h-[30px] w-[52px] flex-none cursor-pointer rounded-full p-[3px] before:absolute before:inset-x-0 before:-inset-y-[7px]',
                    'justify-end bg-accent' => $nudge,
                    'justify-start bg-white/20' => ! $nudge,
                ])
            >
                <span class="size-6 rounded-full bg-paper"></span>
            </button>
        </x-card>
    </div>
</div>
