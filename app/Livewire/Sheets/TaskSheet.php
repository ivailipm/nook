<?php

namespace App\Livewire\Sheets;

use App\Actions\UpdateTask;
use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "Edit task" sheet, rendered once in the layout and opened by the task rows on To-do and Home's Today card.
 * Saving and deleting reread the task, so a double tap or a stale sheet does nothing.
 */
class TaskSheet extends Component
{
    #[Locked]
    public ?int $taskId = null;

    public string $editTaskTitle = '';

    public string $editDueDate = '';

    public ?string $priority = null;

    public string $tag = '';

    public string $repeat = '';

    #[On('show-task')]
    public function show(int $id): void
    {
        $task = auth()->user()->tasks()->findOrFail($id);

        $this->taskId = $task->id;
        $this->editTaskTitle = $task->title;
        $this->editDueDate = $task->due_date?->toDateString() ?? '';
        $this->priority = $task->priority?->value;
        $this->tag = $task->tag->value;
        $this->repeat = $task->repeat_weekday === null ? 'once' : 'weekly';
        $this->resetValidation();

        $this->dispatch('open-sheet', name: 'task');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'editTaskTitle' => ['required', 'string', 'max:255'],
            'editDueDate' => ['nullable', 'date_format:Y-m-d'],
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
        return ['editTaskTitle' => 'task', 'editDueDate' => 'due date'];
    }

    public function save(UpdateTask $updateTask): void
    {
        $task = $this->task();

        if ($task === null) {
            return;
        }

        $this->editTaskTitle = Str::trim($this->editTaskTitle);

        $this->validate();

        $task = $updateTask->handle(
            $task,
            $this->editTaskTitle,
            // A blank date and a blank priority pass the nullable rules, so they count as none.
            filled($this->editDueDate) ? Carbon::parse($this->editDueDate) : null,
            TaskPriority::tryFrom((string) $this->priority),
            TaskTag::from($this->tag),
            $this->repeat === 'weekly',
        );

        $this->dispatch('toast', message: $updateTask->message($task));
        $this->finish($task);
    }

    public function deleteTask(): void
    {
        $task = $this->task();

        if ($task === null) {
            return;
        }

        $task->delete();

        $this->dispatch('toast', message: 'Task deleted');
        $this->finish($task);
        $this->dispatch('nav-counts-changed');
    }

    /**
     * Forgets the task and any unsaved change or error. The view calls this whenever the sheet closes.
     */
    public function clear(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.sheets.task-sheet', ['task' => $this->task()]);
    }

    private function finish(Task $task): void
    {
        $this->clear();

        $this->dispatch('close-sheet', name: 'task');
        $this->dispatch('task-changed', id: $task->id);
    }

    private function task(): ?Task
    {
        return $this->taskId === null ? null : auth()->user()->tasks()->find($this->taskId);
    }
}
