<?php

use App\Enums\TaskTag;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskGroups;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function groupedTask(User $user, string $title, array $attributes = []): Task
{
    return Task::factory()->for($user)->create(['title' => $title, ...$attributes]);
}

/**
 * The groups as title => the titles of their tasks.
 *
 * @return array<string, list<string>>
 */
function taskGroupTitles(User $user, ?string $tag = null): array
{
    $groups = [];

    foreach (TaskGroups::for($user, $tag) as $group) {
        $groups[$group['title']] = $group['tasks']->pluck('title')->all();
    }

    return $groups;
}

it('returns the groups in order', function () {
    groupedTask($this->user, 'done', ['done' => true, 'completed_at' => now()]);
    groupedTask($this->user, 'upcoming', ['due_date' => today()->addDay()]);
    groupedTask($this->user, 'today', ['due_date' => today()]);
    groupedTask($this->user, 'overdue', ['due_date' => today()->subDay()]);

    expect(taskGroupTitles($this->user))->toBe([
        'Overdue' => ['overdue'],
        'Today' => ['today'],
        'Upcoming' => ['upcoming'],
        'Done' => ['done'],
    ]);
});

it('leaves out empty groups', function () {
    groupedTask($this->user, 'today', ['due_date' => today()]);

    expect(taskGroupTitles($this->user))->toBe(['Today' => ['today']]);
});

it('returns no groups without tasks', function () {
    expect(TaskGroups::for($this->user))->toBe([]);
});

it('keeps Overdue, Today and Done in id order', function () {
    groupedTask($this->user, 'recent', ['due_date' => today()->subDay()]);
    groupedTask($this->user, 'older', ['due_date' => today()->subDays(5)]);
    groupedTask($this->user, 'first', ['due_date' => today()]);
    groupedTask($this->user, 'second', ['due_date' => today()]);
    groupedTask($this->user, 'done now', ['done' => true, 'completed_at' => now()]);
    groupedTask($this->user, 'done earlier', ['done' => true, 'completed_at' => now()->subDays(3)]);

    expect(taskGroupTitles($this->user))->toBe([
        'Overdue' => ['recent', 'older'],
        'Today' => ['first', 'second'],
        'Done' => ['done now', 'done earlier'],
    ]);
});

it('sorts Upcoming by date, then id, with no-date tasks last', function () {
    groupedTask($this->user, 'no date 1');
    groupedTask($this->user, 'in 200 days', ['due_date' => today()->addDays(200)]);
    groupedTask($this->user, 'in 3 days, first', ['due_date' => today()->addDays(3)]);
    groupedTask($this->user, 'no date 2');
    groupedTask($this->user, 'tomorrow', ['due_date' => today()->addDay()]);
    groupedTask($this->user, 'in 3 days, second', ['due_date' => today()->addDays(3)]);

    expect(taskGroupTitles($this->user))->toBe([
        'Upcoming' => ['tomorrow', 'in 3 days, first', 'in 3 days, second', 'in 200 days', 'no date 1', 'no date 2'],
    ]);
});

it('keeps a done task for 7 days from its completion', function () {
    groupedTask($this->user, 'exactly 7 days', ['done' => true, 'completed_at' => now()->subDays(7)]);
    groupedTask($this->user, '7 days and a minute', ['done' => true, 'completed_at' => now()->subDays(7)->subMinute()]);
    groupedTask($this->user, 'never completed', ['done' => true, 'completed_at' => null]);

    expect(taskGroupTitles($this->user))->toBe(['Done' => ['exactly 7 days']])
        ->and($this->user->tasks()->count())->toBe(3);
});

it('puts a done task in Done whatever its due date', function () {
    groupedTask($this->user, 'due today', ['due_date' => today(), 'done' => true, 'completed_at' => now()]);
    groupedTask($this->user, 'due last week', ['due_date' => today()->subDays(7), 'done' => true, 'completed_at' => now()]);
    groupedTask($this->user, 'due next week', ['due_date' => today()->addDays(7), 'done' => true, 'completed_at' => now()]);

    expect(taskGroupTitles($this->user))->toBe(['Done' => ['due today', 'due last week', 'due next week']]);
});

it('filters every group by tag', function () {
    foreach (array_column(TaskTag::cases(), 'value') as $tag) {
        groupedTask($this->user, "overdue {$tag}", ['tag' => $tag, 'due_date' => today()->subDay()]);
        groupedTask($this->user, "today {$tag}", ['tag' => $tag, 'due_date' => today()]);
        groupedTask($this->user, "upcoming {$tag}", ['tag' => $tag, 'due_date' => today()->addDay()]);
        groupedTask($this->user, "no date {$tag}", ['tag' => $tag]);
        groupedTask($this->user, "done {$tag}", ['tag' => $tag, 'done' => true, 'completed_at' => now()]);
    }

    expect(taskGroupTitles($this->user, 'school'))->toBe([
        'Overdue' => ['overdue school'],
        'Today' => ['today school'],
        'Upcoming' => ['upcoming school', 'no date school'],
        'Done' => ['done school'],
    ]);
});

it('leaves out groups the tag empties', function () {
    groupedTask($this->user, 'home', ['tag' => 'home', 'due_date' => today()]);
    groupedTask($this->user, 'school', ['tag' => 'school', 'due_date' => today()->addDay()]);

    expect(taskGroupTitles($this->user, 'school'))->toBe(['Upcoming' => ['school']])
        ->and(taskGroupTitles($this->user, 'internship'))->toBe([]);
});

it('never returns another user\'s tasks', function () {
    $other = User::factory()->create();

    groupedTask($other, 'overdue', ['due_date' => today()->subDay()]);
    groupedTask($other, 'today', ['due_date' => today()]);
    groupedTask($other, 'upcoming', ['due_date' => today()->addDay()]);
    groupedTask($other, 'no date');
    groupedTask($other, 'done', ['done' => true, 'completed_at' => now()]);

    expect(taskGroupTitles($this->user))->toBe([]);
});
