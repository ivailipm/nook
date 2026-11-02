<?php

namespace App\Livewire\Sheets;

use App\Actions\CreateTask;
use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The "New task" sheet, rendered once in the layout and opened by the To-do FAB and the chooser.
 */
class AddTask extends Component
{
    public string $taskTitle = '';

    public string $due = 'today';

    public ?string $priority = null;

    public string $tag = TaskTag::Home->value;

    public string $repeat = 'once';

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'taskTitle' => ['required', 'string', 'max:255'],
            'due' => ['required', Rule::in(['today', 'tomorrow', 'weekday', 'next-week', 'none'])],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'tag' => ['required', Rule::enum(TaskTag::class)],
            'repeat' => ['required', Rule::in(['once', 'weekly'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['taskTitle' => 'task'];
    }

    public function save(CreateTask $createTask): void
    {
        $this->taskTitle = Str::trim($this->taskTitle);

        $this->validate();

        $dueDate = match ($this->due) {
            'today' => today(),
            'tomorrow' => today()->addDay(),
            'weekday' => today()->addDays(3),
            'next-week' => today()->addDays(7),
            default => null,
        };
        $repeatWeekday = null;

        // A weekly task repeats on its due date's weekday, so it needs a date: without one it starts today.
        if ($this->repeat === 'weekly') {
            $dueDate ??= today();
            $repeatWeekday = $dueDate->dayOfWeek;
        }

        $task = $createTask->handle(
            auth()->user(),
            $this->taskTitle,
            $dueDate,
            // An empty string passes the nullable rule, so it counts as no priority.
            TaskPriority::tryFrom((string) $this->priority),
            TaskTag::from($this->tag),
            $repeatWeekday,
        );

        $this->resetForm();

        $this->dispatch('toast', message: $createTask->message($task));
        $this->dispatch('task-added');
        $this->dispatch('close-sheet', name: 'add-task');
    }

    /**
     * Back to an empty task due today, without errors. The view calls this whenever the sheet closes.
     */
    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.sheets.add-task');
    }
}
