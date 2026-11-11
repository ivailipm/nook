<?php

namespace App\Actions;

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Models\Task;
use App\Support\RelativeDate;
use Carbon\CarbonInterface;

/**
 * Saves the edit-task sheet's validated fields onto the task. It never touches done, completed_at or rolled_over,
 * and changes only this row, never a recurring task's earlier or next copies. A past date stays as given.
 */
class UpdateTask
{
    public function handle(Task $task, string $title, ?CarbonInterface $dueDate, ?TaskPriority $priority, TaskTag $tag, bool $weekly): Task
    {
        $repeatWeekday = null;

        // A weekly task repeats on its due date's weekday, so it needs a date: without one it starts today. While the
        // date stays the same, so does the weekday, so a title-only edit keeps an "every tue" task that is due on a Thursday.
        if ($weekly) {
            $dueDate ??= today();
            $repeatWeekday = $task->repeat_weekday !== null && $task->due_date?->toDateString() === $dueDate->toDateString()
                ? $task->repeat_weekday
                : $dueDate->dayOfWeek;
        }

        $task->update([
            'title' => $title,
            'tag' => $tag,
            'due_date' => $dueDate,
            'priority' => $priority,
            'repeat_weekday' => $repeatWeekday,
        ]);

        return $task;
    }

    /**
     * The toast after saving: "Task saved · due tomorrow", or "Task saved" without a due date.
     */
    public function message(Task $task): string
    {
        return $task->due_date === null
            ? 'Task saved'
            : 'Task saved · due '.RelativeDate::format($task->due_date);
    }
}
