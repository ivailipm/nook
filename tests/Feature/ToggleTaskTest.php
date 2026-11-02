<?php

use App\Actions\ToggleTask;
use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));
});

it('gives the next task its due date', function (?string $due, int $weekday, string $next) {
    $task = Task::factory()->create(['due_date' => $due, 'repeat_weekday' => $weekday]);

    expect(app(ToggleTask::class)->handle($task)->fresh()->due_date->toDateString())->toBe($next);
})->with([
    'the plain case' => ['2026-10-29', 4, '2026-11-05'],
    'overdue, but + 7 is still ahead' => ['2026-10-28', 3, '2026-11-04'],
    '+ 7 is today, which is not in the past' => ['2026-10-22', 4, '2026-10-29'],
    '+ 7 is past, so the next Tuesday' => ['2026-10-20', 2, '2026-11-03'],
    'past, and today is that weekday: a week ahead' => ['2026-10-01', 4, '2026-11-05'],
    'past, with Sunday as 0' => ['2026-10-11', 0, '2026-11-01'],
    'past, and repeat_weekday decides, not the old date\'s weekday' => ['2026-10-02', 2, '2026-11-03'],
    'ticked early' => ['2026-11-03', 2, '2026-11-10'],
    'no due date counts as today' => [null, 4, '2026-11-05'],
]);

it('keeps the weekday across the clock change', function () {
    // Wednesday 21 October 2026; the clocks go back on Sunday 25 October.
    $this->travelTo(Carbon::parse('2026-10-21 12:00', 'Europe/Brussels'));

    $task = Task::factory()->create(['due_date' => '2026-10-21', 'repeat_weekday' => 3]);

    expect(app(ToggleTask::class)->handle($task)->fresh()->due_date->toDateString())->toBe('2026-10-28');
});

it('completes a recurring task and creates the next one for the same user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->for($user)->create([
        'title' => 'Put out the PMD bag',
        'tag' => TaskTag::School,
        'due_date' => '2026-10-29',
        'priority' => TaskPriority::High,
        'repeat_weekday' => 4,
    ]);

    $next = app(ToggleTask::class)->handle($task);

    // The task passed in shows the new state, as does its row.
    foreach ([$task, $task->fresh()] as $completed) {
        expect($completed->done)->toBeTrue()
            ->and($completed->completed_at->toDateTimeString())->toBe('2026-10-29 12:00:00')
            ->and($completed->rolled_over)->toBeTrue();
    }

    $this->assertModelExists($next);

    $next = $next->fresh();

    expect($next->is($task))->toBeFalse()
        ->and($next->user->is($user))->toBeTrue()
        ->and($next->title)->toBe('Put out the PMD bag')
        ->and($next->tag)->toBe(TaskTag::School)
        ->and($next->priority)->toBe(TaskPriority::High)
        ->and($next->repeat_weekday)->toBe(4)
        ->and($next->done)->toBeFalse()
        ->and($next->completed_at)->toBeNull()
        ->and($next->rolled_over)->toBeFalse()
        ->and(Task::count())->toBe(2)
        ->and($other->tasks()->count())->toBe(0);
});

it('builds the toast with the long date', function () {
    $task = Task::factory()->create(['due_date' => '2026-10-29', 'repeat_weekday' => 4]);
    $action = app(ToggleTask::class);

    expect($action->message($action->handle($task)))->toBe('Done. Next one: Thu 5 Nov');
});

it('completes a one-off task without creating another', function () {
    $task = Task::factory()->create(['due_date' => '2026-10-29']);

    expect(app(ToggleTask::class)->handle($task))->toBeNull()
        ->and($task->done)->toBeTrue()
        ->and($task->completed_at->toDateTimeString())->toBe('2026-10-29 12:00:00')
        ->and($task->rolled_over)->toBeFalse()
        ->and($task->fresh()->done)->toBeTrue()
        ->and(Task::count())->toBe(1);
});

it('unticks a task and leaves the next one in place', function () {
    $task = Task::factory()->create(['due_date' => '2026-10-29', 'repeat_weekday' => 4]);
    $action = app(ToggleTask::class);
    $next = $action->handle($task);

    expect($action->handle($task))->toBeNull()
        ->and($task->done)->toBeFalse()
        ->and($task->completed_at)->toBeNull()
        ->and($task->rolled_over)->toBeTrue()
        ->and($task->fresh()->done)->toBeFalse()
        ->and(Task::count())->toBe(2)
        ->and($next->fresh()->done)->toBeFalse()
        ->and($next->fresh()->due_date->toDateString())->toBe('2026-11-05');
});

it('does not create a second copy on tick, untick, tick', function () {
    $task = Task::factory()->create(['due_date' => '2026-10-29', 'repeat_weekday' => 4]);
    $action = app(ToggleTask::class);

    $action->handle($task);
    $action->handle($task);

    $this->travelTo(Carbon::parse('2026-10-29 15:30', 'Europe/Brussels'));

    expect($action->handle($task))->toBeNull()
        ->and($task->done)->toBeTrue()
        ->and($task->completed_at->toDateTimeString())->toBe('2026-10-29 15:30:00')
        ->and(Task::count())->toBe(2);
});

it('decides from the row, so a second tap with a stale model does not create a second copy', function () {
    $task = Task::factory()->create(['due_date' => '2026-10-29', 'repeat_weekday' => 4]);
    $staleTask = $task->fresh();
    $action = app(ToggleTask::class);

    $action->handle($task);

    expect($action->handle($staleTask))->toBeNull()
        ->and($staleTask->done)->toBeFalse()
        ->and($staleTask->rolled_over)->toBeTrue()
        ->and(Task::count())->toBe(2);
});

it('rolls over again when the next task is ticked', function () {
    $task = Task::factory()->create(['due_date' => '2026-10-29', 'repeat_weekday' => 4]);
    $action = app(ToggleTask::class);

    $next = $action->handle($task);
    $third = $action->handle($next);

    expect($third->due_date->toDateString())->toBe('2026-11-12')
        ->and($next->rolled_over)->toBeTrue()
        ->and(Task::count())->toBe(3);
});
