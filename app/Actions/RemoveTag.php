<?php

namespace App\Actions;

use App\Enums\TaskTag;
use App\Models\CustomTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Removes a task tag of the user's own and builds its message. Its tasks move to home, the quick add's default tag,
 * so nothing drops off the list.
 */
class RemoveTag
{
    /**
     * @return int How many tasks moved to home.
     */
    public function handle(User $user, CustomTag $tag): int
    {
        return DB::transaction(function () use ($user, $tag) {
            $moved = $user->tasks()->where('tag', $tag->name)->update(['tag' => TaskTag::Home->value]);

            $tag->delete();

            return $moved;
        });
    }

    /**
     * The message after removing: "Removed the gym tag", or with what moved, "Removed the gym tag · 2 tasks moved to home".
     */
    public function message(string $name, int $tasks): string
    {
        return $tasks === 0
            ? "Removed the {$name} tag"
            : "Removed the {$name} tag · {$tasks} ".Str::plural('task', $tasks).' moved to home';
    }
}
