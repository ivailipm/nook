<?php

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

it('persists a factory task as an open, one-off task for a user', function () {
    $task = Task::factory()->create()->fresh();

    $this->assertModelExists($task);

    expect($task->user->is(User::sole()))->toBeTrue()
        ->and($task->title)->not->toBe('')
        ->and($task->tag)->toBeInstanceOf(TaskTag::class)
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

    expect($task->tag)->toBe(TaskTag::Internship)
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
