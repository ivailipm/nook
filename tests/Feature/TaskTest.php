<?php

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));
});

it('persists a factory task as an open, one-off task for a user', function () {
    $task = Task::factory()->create()->fresh();

    $this->assertModelExists($task);

    expect($task->user->is(User::sole()))->toBeTrue()
        ->and($task->title)->not->toBe('')
        ->and($task->tag)->toBeIn(array_column(TaskTag::cases(), 'value'))
        ->and($task->due_date)->toBeNull()
        ->and($task->priority)->toBeNull()
        ->and($task->repeat_weekday)->toBeNull()
        ->and($task->done)->toBeFalse()
        ->and($task->completed_at)->toBeNull()
        ->and($task->rolled_over)->toBeFalse();
});

it('casts its columns when read back', function () {
    $task = Task::factory()->create([
        'tag' => 'internship',
        'priority' => 'med',
        'due_date' => '2026-10-29',
        'repeat_weekday' => 0,
        'done' => true,
        'completed_at' => '2026-10-29 12:00:00',
        'rolled_over' => true,
    ])->fresh();

    expect($task->tag)->toBe('internship')
        ->and($task->priority)->toBe(TaskPriority::Med)
        ->and($task->due_date)->toBeInstanceOf(Carbon::class)
        ->and($task->due_date->toDateString())->toBe('2026-10-29')
        ->and($task->repeat_weekday)->toBe(0)
        ->and($task->done)->toBeTrue()
        ->and($task->completed_at)->toBeInstanceOf(Carbon::class)
        ->and($task->completed_at->toDateTimeString())->toBe('2026-10-29 12:00:00')
        ->and($task->rolled_over)->toBeTrue();
});

it('lists only the user\'s own tasks', function () {
    $user = User::factory()->create();
    $tasks = Task::factory()->count(2)->for($user)->create();
    Task::factory()->create();

    expect($user->tasks->modelKeys())->toEqualCanonicalizing($tasks->modelKeys());
});

it('deletes the user\'s tasks with the user', function () {
    $user = User::factory()->create();
    Task::factory()->for($user)->create();
    $other = Task::factory()->create();

    $user->delete();

    expect(Task::pluck('id')->all())->toBe([$other->id]);
});

it('labels the due date', function (?int $days, bool $done, string $label) {
    $task = Task::factory()->create(['due_date' => $days === null ? null : today()->addDays($days), 'done' => $done])->fresh();

    expect($task->dueLabel())->toBe($label);
})->with([
    'no date' => [null, false, 'no date'],
    'open, yesterday' => [-1, false, 'overdue · was yesterday'],
    'open, 2 days ago' => [-2, false, 'overdue · was 27 Oct'],
    'open, today' => [0, false, 'due today'],
    'open, tomorrow' => [1, false, 'due tomorrow'],
    'open, in 2 days' => [2, false, 'due Sat'],
    'open, in 6 days' => [6, false, 'due Wed'],
    'open, in 7 days' => [7, false, 'due 5 Nov'],
    'done, yesterday' => [-1, true, 'due yesterday'],
    'done, 3 days ago' => [-3, true, 'due 26 Oct'],
    'done, today' => [0, true, 'due today'],
]);

it('labels the repeat', function (?int $weekday, ?string $label) {
    expect(Task::factory()->create(['repeat_weekday' => $weekday])->fresh()->repeatLabel())->toBe($label);
})->with([
    'Sunday is 0' => [0, 'every Sunday'],
    'Thursday' => [4, 'every Thursday'],
    'Saturday' => [6, 'every Saturday'],
    'a one-off' => [null, null],
]);

it('counts the days until the due date', function (?int $days) {
    $task = Task::factory()->create(['due_date' => $days === null ? null : today()->addDays($days)])->fresh();

    expect($task->daysUntilDue())->toBe($days);
})->with(['past' => -2, 'today' => 0, 'ahead' => 7, 'no date' => [null]]);

it('is overdue only while open and due before today', function (?int $days, bool $done, bool $overdue) {
    $task = Task::factory()->create(['due_date' => $days === null ? null : today()->addDays($days), 'done' => $done])->fresh();

    expect($task->isOverdue())->toBe($overdue);
})->with([
    'open, yesterday' => [-1, false, true],
    'open, today' => [0, false, false],
    'open, tomorrow' => [1, false, false],
    'open, no date' => [null, false, false],
    'done, yesterday' => [-1, true, false],
    'done, today' => [0, true, false],
]);

it('labels each priority', function (TaskPriority $priority, string $label) {
    expect($priority->label())->toBe($label);
})->with([
    [TaskPriority::High, 'High'],
    [TaskPriority::Med, 'Medium'],
    [TaskPriority::Low, 'Low'],
]);

it('gives the Home card its meta line', function (string $tag, ?int $days, bool $done, string $meta) {
    $task = Task::factory()->create(['tag' => $tag, 'due_date' => $days === null ? null : today()->addDays($days), 'done' => $done])->fresh();

    expect($task->homeMeta())->toBe($meta);
})->with([
    'overdue' => ['school', -1, false, 'School · overdue'],
    'due today' => ['internship', 0, false, 'Internship · due today'],
    'done' => ['home', 0, true, 'Home · done'],
    'overdue and done' => ['home', -3, true, 'Home · done'],
    'due later' => ['school', 2, false, 'School · due Sat'],
    'no date' => ['home', null, false, 'Home · no date'],
]);
