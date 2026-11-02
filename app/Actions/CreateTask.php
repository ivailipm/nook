<?php

namespace App\Actions;

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Models\Task;
use App\Models\User;
use App\Support\RelativeDate;
use Carbon\CarbonInterface;

/**
 * Adds an open task to the user's list. The quick add and the add-task sheet both add through here.
 */
class CreateTask
{
    public function handle(User $user, string $title, ?CarbonInterface $dueDate, ?TaskPriority $priority, TaskTag $tag, ?int $repeatWeekday): Task
    {
        return $user->tasks()->create([
            'title' => $title,
            'tag' => $tag,
            'due_date' => $dueDate,
            'priority' => $priority,
            'repeat_weekday' => $repeatWeekday,
            'done' => false,
            'completed_at' => null,
            'rolled_over' => false,
        ]);
    }

    /**
     * The toast after adding the task: "Task added · due tomorrow", or "Task added" without a due date.
     */
    public function message(Task $task): string
    {
        return $task->due_date === null
            ? 'Task added'
            : 'Task added · due '.RelativeDate::format($task->due_date);
    }
}
