<?php

namespace App\Actions;

use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Ticks or unticks a task. The first tick of a recurring task also creates the next one, and rolled_over
 * remembers that, so unticking and re-ticking doesn't create a second copy. Unticking leaves the next one in place.
 */
class ToggleTask
{
    /**
     * @return Task|null The next task, when this tick created one.
     */
    public function handle(Task $task): ?Task
    {
        $next = DB::transaction(function () use ($task) {
            // Decide from the locked row, so two quick taps can't both see an open task.
            $current = Task::lockForUpdate()->findOrFail($task->id);

            if ($current->done) {
                $current->update(['done' => false, 'completed_at' => null]);

                return null;
            }

            $rollsOver = $current->repeat_weekday !== null && ! $current->rolled_over;

            $current->update([
                'done' => true,
                'completed_at' => now(),
                'rolled_over' => $current->rolled_over || $rollsOver,
            ]);

            return $rollsOver ? $this->createNext($current) : null;
        });

        $task->refresh();

        return $next;
    }

    /**
     * The toast after a tick created the next task: "Done. Next one: Thu 5 Nov".
     */
    public function message(Task $next): string
    {
        return 'Done. Next one: '.$next->due_date->format('D j M');
    }

    private function createNext(Task $completed): Task
    {
        return $completed->user->tasks()->create([
            'title' => $completed->title,
            'tag' => $completed->tag,
            'due_date' => $this->nextDueDate($completed),
            'priority' => $completed->priority,
            'repeat_weekday' => $completed->repeat_weekday,
            'done' => false,
            'completed_at' => null,
            'rolled_over' => false,
        ]);
    }

    /**
     * 7 days after the completed task's due date, or after today without one.
     * If that's still in the past, the first date after today on the task's repeat weekday.
     */
    private function nextDueDate(Task $completed): CarbonInterface
    {
        $date = ($completed->due_date ?? today())->addDays(7);

        return $date->lt(today()) ? today()->next($completed->repeat_weekday) : $date;
    }
}
