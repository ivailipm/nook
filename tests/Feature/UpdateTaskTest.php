<?php

use App\Actions\ToggleTask;
use App\Actions\UpdateTask;
use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
});

/**
 * Saves the task through the action, with the date as Y-m-d or null.
 */
function updateTaskSave(Task $task, ?string $due, bool $weekly, string $title = 'Put out the bins', ?TaskPriority $priority = null, string $tag = 'home'): Task
{
    return app(UpdateTask::class)->handle($task, $title, $due === null ? null : Carbon::parse($due), $priority, $tag, $weekly);
}

it('works out the due date and the repeat', function (?string $dueBefore, ?int $weekdayBefore, ?string $due, bool $weekly, ?string $dueAfter, ?int $weekdayAfter) {
    $task = Task::factory()->for($this->user)->create(['due_date' => $dueBefore, 'repeat_weekday' => $weekdayBefore]);

    updateTaskSave($task, $due, $weekly);

    $fresh = $task->fresh();

    expect([$fresh->due_date?->toDateString(), $fresh->repeat_weekday])->toBe([$dueAfter, $weekdayAfter]);
})->with([
    'a one-off moves' => ['2026-10-29', null, '2026-10-30', false, '2026-10-30', null],
    'a past date is kept' => ['2026-10-28', null, '2026-10-28', false, '2026-10-28', null],
    'title-only edit' => ['2026-10-29', 4, '2026-10-29', true, '2026-10-29', 4],
    '"every tue today" keeps its weekday' => ['2026-10-29', 2, '2026-10-29', true, '2026-10-29', 2],
    'no date resolves to the same day, so it\'s kept' => ['2026-10-29', 2, null, true, '2026-10-29', 2],
    'the weekday follows a moved date' => ['2026-10-29', 4, '2026-10-30', true, '2026-10-30', 5],
    'a one-off turned weekly' => ['2026-10-29', null, '2026-10-29', true, '2026-10-29', 4],
    'a weekly turned one-off' => ['2026-10-29', 4, '2026-10-29', false, '2026-10-29', null],
    'weekly with no date starts today' => ['2026-11-03', 2, null, true, '2026-10-29', 4],
    'the same from no date' => [null, null, null, true, '2026-10-29', 4],
    'a weekly turned undated one-off' => ['2026-10-29', 4, null, false, null, null],
]);

it('returns the task it was given, with its new fields stored', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Bins', 'tag' => 'home', 'priority' => TaskPriority::Low, 'due_date' => '2026-10-29']);

    $saved = updateTaskSave($task, '2026-10-30', false, 'Put out the bins', TaskPriority::High, 'school');
    $fresh = $task->fresh();

    expect($saved->is($task))->toBeTrue()
        ->and([$fresh->title, $fresh->tag, $fresh->priority, $fresh->due_date->toDateString()])
        ->toBe(['Put out the bins', 'school', TaskPriority::High, '2026-10-30']);
});

it('clears the priority', function () {
    $task = Task::factory()->for($this->user)->create(['priority' => TaskPriority::High]);

    updateTaskSave($task, null, false);

    expect($task->fresh()->priority)->toBeNull();
});

it('keeps a done, rolled-over task done and leaves every other task alone', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Bins', 'tag' => 'home', 'due_date' => '2026-10-29', 'repeat_weekday' => 4]);
    $next = app(ToggleTask::class)->handle($task, true);
    Task::factory()->for($this->user)->create(['due_date' => '2026-10-30']);
    Task::factory()->create(['due_date' => '2026-10-29']);
    Task::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $others = fn () => Task::whereKeyNot($task->id)->orderBy('id')->get()->map->getAttributes()->all();
    $before = $others();

    updateTaskSave($task->fresh(), '2026-10-30', true, 'Put out the bins', TaskPriority::High, 'school');

    $fresh = $task->fresh();

    expect([$fresh->title, $fresh->due_date->toDateString(), $fresh->repeat_weekday])->toBe(['Put out the bins', '2026-10-30', 5])
        ->and([$fresh->done, $fresh->rolled_over, $fresh->user_id])->toBe([true, true, $this->user->id])
        ->and($fresh->completed_at->toDateTimeString())->toBe('2026-10-29 12:00:00')
        ->and($others())->toBe($before)
        ->and($next->fresh()->due_date->toDateString())->toBe('2026-11-05')
        ->and(Task::count())->toBe(4);
});

it('makes no second copy when a done weekly task goes to once and back, then is unticked and reticked', function () {
    $task = Task::factory()->for($this->user)->create(['due_date' => '2026-10-29', 'repeat_weekday' => 4]);
    $toggleTask = app(ToggleTask::class);

    $toggleTask->handle($task, true);

    updateTaskSave($task, '2026-10-29', false);
    updateTaskSave($task, '2026-10-29', true);

    expect($toggleTask->handle($task, false))->toBeNull()
        ->and($toggleTask->handle($task, true))->toBeNull()
        ->and($task->fresh()->done)->toBeTrue()
        ->and($task->fresh()->rolled_over)->toBeTrue()
        ->and($this->user->tasks()->count())->toBe(2);
});

it('rolls a moved weekly task over from its new date', function () {
    $task = Task::factory()->for($this->user)->create(['due_date' => '2026-10-29', 'repeat_weekday' => 4]);
    $toggleTask = app(ToggleTask::class);

    updateTaskSave($task, '2026-10-30', true);

    $next = $toggleTask->handle($task, true);

    expect($toggleTask->message($next))->toBe('Done. Next one: Fri 6 Nov')
        ->and($next->repeat_weekday)->toBe(5)
        ->and($next->due_date->toDateString())->toBe('2026-11-06');
});

it('builds the toast from the due date', function (?string $due, string $toast) {
    $task = updateTaskSave(Task::factory()->for($this->user)->create(), $due, false);

    expect(app(UpdateTask::class)->message($task))->toBe($toast);
})->with([
    'today' => ['2026-10-29', 'Task saved · due today'],
    'tomorrow' => ['2026-10-30', 'Task saved · due tomorrow'],
    'yesterday' => ['2026-10-28', 'Task saved · due yesterday'],
    'Sun 1 Nov' => ['2026-11-01', 'Task saved · due Sun'],
    '5 Nov' => ['2026-11-05', 'Task saved · due 5 Nov'],
    'no date' => [null, 'Task saved'],
]);
