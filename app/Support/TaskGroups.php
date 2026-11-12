<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The To-do page's groups: Overdue, Today, Upcoming and Done, leaving out empty ones.
 */
class TaskGroups
{
    /**
     * Done holds the tasks completed in the last 7 days. Older ones stay in the database, off the list.
     *
     * @return list<array{title: string, tasks: Collection<int, Task>}>
     */
    public static function for(User $user, ?string $tag = null): array
    {
        $today = today()->toDateString();
        $tasks = fn () => $user->tasks()->when($tag !== null, fn (Builder $query) => $query->where('tag', $tag));
        $open = fn () => $tasks()->where('done', false);

        $groups = [
            ['title' => 'Overdue', 'tasks' => $open()->where('due_date', '<', $today)->orderBy('id')->get()],
            ['title' => 'Today', 'tasks' => $open()->where('due_date', $today)->orderBy('id')->get()],
            ['title' => 'Upcoming', 'tasks' => $open()
                ->where(fn (Builder $query) => $query->where('due_date', '>', $today)->orWhereNull('due_date'))
                ->orderByRaw('due_date asc nulls last')
                ->orderBy('id')
                ->get()],
            ['title' => 'Done', 'tasks' => $tasks()->where('done', true)->where('completed_at', '>=', now()->subDays(7))->orderBy('id')->get()],
        ];

        return array_values(array_filter($groups, fn (array $group) => $group['tasks']->isNotEmpty()));
    }
}
