<?php

use App\Enums\TaskPriority;
use App\Livewire\Sheets\AddTask;
use App\Livewire\Sheets\TaskSheet;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\TaskSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->withoutVite();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    app(TaskSeeder::class)->run($this->user);
});

function taskSheetRow(string $title): Task
{
    return test()->user->tasks()->where('title', $title)->sole();
}

function openTaskSheet(Task $task): Testable
{
    return Livewire::test(TaskSheet::class)->dispatch('show-task', id: $task->id);
}

function taskSheetHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * Each chip group's label and the text of its pressed chips.
 *
 * @return array<string, list<string>>
 */
function taskSheetPressed(Testable $component): array
{
    $page = taskSheetHtml($component);
    $groups = [];

    foreach ($page->querySelectorAll('[role="group"]') as $group) {
        $groups[trim($page->getElementById($group->getAttribute('aria-labelledby'))->textContent)] = array_map(
            fn (Element $chip) => trim($chip->textContent),
            iterator_to_array($group->querySelectorAll('button[aria-pressed="true"]')),
        );
    }

    return $groups;
}

/**
 * Every task's stored attributes, in id order.
 *
 * @return list<array<string, mixed>>
 */
function taskSheetSnapshot(): array
{
    return Task::orderBy('id')->get()->map->getAttributes()->all();
}

// Opening

it('opens on show-task with a labelled title, the close wrapper and the form in order', function () {
    $component = openTaskSheet(taskSheetRow('Return library book'))->assertDispatched('open-sheet', name: 'task');
    $page = taskSheetHtml($component);
    $dialog = $page->querySelector('dialog#sheet-task');
    $form = $dialog->querySelector('form');
    $title = $page->getElementById('editTaskTitle');
    $date = $page->getElementById('editDueDate');
    $buttons = iterator_to_array($form->querySelectorAll('form > button'));
    $children = array_values(array_filter(iterator_to_array($form->childNodes), fn (Node $node) => $node instanceof Element));

    expect(trim($page->getElementById($dialog->getAttribute('aria-labelledby'))->textContent))->toBe('Edit task')
        ->and($dialog->parentElement->getAttribute('x-on:close.capture'))->toBe('$wire.clear()')
        ->and($form->getAttribute('wire:submit'))->toBe('save')
        ->and(trim($dialog->querySelector('label[for="editTaskTitle"]')->textContent))->toBe('Task')
        ->and([$title->getAttribute('wire:model'), $title->getAttribute('maxlength')])->toBe(['editTaskTitle', '255'])
        ->and(trim($dialog->querySelector('label[for="editDueDate"]')->textContent))->toBe('Or pick a date')
        ->and([$date->getAttribute('type'), $date->getAttribute('wire:model.live')])->toBe(['date', 'editDueDate'])
        ->and(array_map(fn (Element $group) => trim($page->getElementById($group->getAttribute('aria-labelledby'))->textContent), iterator_to_array($dialog->querySelectorAll('[role="group"]'))))
        ->toBe(['Due', 'Priority', 'Tag', 'Repeat'])
        ->and(array_map(fn (Element $child) => trim(($child->firstElementChild ?? $child)->textContent), $children))
        ->toBe(['Task', 'Due', 'Or pick a date', 'Priority', 'Tag', 'Repeat', 'Save changes', 'Delete task'])
        ->and(array_map(fn (Element $button) => [trim($button->textContent), $button->getAttribute('type'), $button->getAttribute('class')], $buttons))->toBe([
            ['Save changes', 'submit', 'min-h-12 cursor-pointer rounded-button bg-apricot text-[15px] font-bold text-on-apricot'],
            ['Delete task', 'button', 'min-h-[46px] cursor-pointer rounded-button border border-destructive-border text-[14px] font-bold text-destructive'],
        ])
        ->and($form->lastElementChild->isSameNode($buttons[1]))->toBeTrue()
        ->and($buttons[1]->getAttribute('wire:click'))->toBe('deleteTask');
});

it('renders no form until a task is shown', function () {
    $page = taskSheetHtml(Livewire::test(TaskSheet::class));

    expect($page->querySelector('dialog#sheet-task'))->not->toBeNull()
        ->and($page->querySelector('dialog#sheet-task form'))->toBeNull();
});

it('fills the form from the task it was opened for and changes nothing', function (string $title, array $properties, array $pressed) {
    Task::factory()->for($this->user)->create(['title' => 'Email the prof', 'due_date' => null, 'priority' => TaskPriority::High, 'tag' => 'internship']);
    Task::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $before = taskSheetSnapshot();

    $component = openTaskSheet(taskSheetRow($title))
        ->assertDispatched('open-sheet', name: 'task')
        ->assertHasNoErrors();

    foreach ($properties as $property => $value) {
        $component->assertSet($property, $value);
    }

    expect(taskSheetPressed($component))->toBe($pressed)
        ->and(taskSheetSnapshot())->toBe($before);
})->with([
    'Return library book, overdue' => ['Return library book', ['editTaskTitle' => 'Return library book', 'editDueDate' => '2026-10-28', 'priority' => 'med', 'tag' => 'school', 'repeat' => 'once'], ['Due' => [], 'Priority' => ['Medium'], 'Tag' => ['school'], 'Repeat' => ['Once']]],
    'Put out the PMD bag, weekly' => ['Put out the PMD bag', ['editTaskTitle' => 'Put out the PMD bag', 'editDueDate' => '2026-10-29', 'priority' => null, 'tag' => 'home', 'repeat' => 'weekly'], ['Due' => ['Today'], 'Priority' => ['None'], 'Tag' => ['home'], 'Repeat' => ['Every week']]],
    'Water the plants, done' => ['Water the plants', ['editTaskTitle' => 'Water the plants', 'editDueDate' => '2026-10-29', 'priority' => 'low', 'tag' => 'home', 'repeat' => 'once'], ['Due' => ['Today'], 'Priority' => ['Low'], 'Tag' => ['home'], 'Repeat' => ['Once']]],
    'Take out the trash, weekly on 3 Nov' => ['Take out the trash', ['editTaskTitle' => 'Take out the trash', 'editDueDate' => '2026-11-03', 'priority' => null, 'tag' => 'home', 'repeat' => 'weekly'], ['Due' => [], 'Priority' => ['None'], 'Tag' => ['home'], 'Repeat' => ['Every week']]],
    'a task with no date' => ['Email the prof', ['editTaskTitle' => 'Email the prof', 'editDueDate' => '', 'priority' => 'high', 'tag' => 'internship', 'repeat' => 'once'], ['Due' => ['No date'], 'Priority' => ['High'], 'Tag' => ['internship'], 'Repeat' => ['Once']]],
]);

it('presses the Due chip that matches the date', function (string $date, array $pressed) {
    $component = openTaskSheet(taskSheetRow('Return library book'))->set('editDueDate', $date);

    expect(taskSheetPressed($component)['Due'])->toBe($pressed);
})->with([
    'today' => ['2026-10-29', ['Today']],
    'tomorrow' => ['2026-10-30', ['Tomorrow']],
    'in three days' => ['2026-11-01', ['Sun']],
    'next week' => ['2026-11-05', ['Next week']],
    'no date' => ['', ['No date']],
    '12 Nov, which no chip sets' => ['2026-11-12', []],
    'yesterday' => ['2026-10-28', []],
]);

it('sets the date from each Due chip', function () {
    $chips = taskSheetHtml(openTaskSheet(taskSheetRow('Return library book')))->querySelectorAll('[role="group"][aria-labelledby="edit-task-due"] button');

    expect(array_map(fn (Element $chip) => [trim($chip->textContent), $chip->getAttribute('wire:click')], iterator_to_array($chips)))->toBe([
        ['Today', "\$set('editDueDate', '2026-10-29')"],
        ['Tomorrow', "\$set('editDueDate', '2026-10-30')"],
        ['Sun', "\$set('editDueDate', '2026-11-01')"],
        ['Next week', "\$set('editDueDate', '2026-11-05')"],
        ['No date', "\$set('editDueDate', '')"],
    ]);
});

// Saving

it('saves every field of the form, with a toast, and closes', function () {
    $book = taskSheetRow('Return library book');

    openTaskSheet($book)
        ->set('editTaskTitle', '  Return library books  ')
        ->set('editDueDate', '2026-10-30')
        ->set('priority', 'high')
        ->set('tag', 'home')
        ->set('repeat', 'once')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Task saved · due tomorrow')
        ->assertDispatched('close-sheet', name: 'task')
        ->assertDispatched('task-changed', id: $book->id)
        ->assertNotDispatched('nav-counts-changed')
        ->assertSet('taskId', null);

    $fresh = $book->fresh();

    expect([$fresh->title, $fresh->due_date->toDateString(), $fresh->priority, $fresh->tag, $fresh->repeat_weekday])
        ->toBe(['Return library books', '2026-10-30', TaskPriority::High, 'home', null])
        ->and([$fresh->done, $fresh->completed_at, $fresh->rolled_over, $fresh->user_id])->toBe([false, null, false, $this->user->id]);
});

it('saves a blank priority as none', function () {
    $book = taskSheetRow('Return library book');

    openTaskSheet($book)
        ->set('priority', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($book->fresh()->priority)->toBeNull();
});

it('saves a 255-character title', function () {
    $book = taskSheetRow('Return library book');

    openTaskSheet($book)
        ->set('editTaskTitle', str_repeat('a', 255))
        ->call('save')
        ->assertHasNoErrors();

    expect($book->fresh()->title)->toBe(str_repeat('a', 255));
});

it('saves a past date as given', function () {
    $book = taskSheetRow('Return library book');

    openTaskSheet($book)
        ->set('editDueDate', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Task saved · due 1 Oct');

    expect($book->fresh()->due_date->toDateString())->toBe('2026-10-01');
});

it('saves no date, with the plain toast', function () {
    $report = taskSheetRow('Finish cloud lab report');

    openTaskSheet($report)
        ->set('editDueDate', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Task saved');

    expect($report->fresh()->due_date)->toBeNull();
});

it('keeps a done task done', function () {
    $plants = taskSheetRow('Water the plants');
    $completedAt = $plants->completed_at->toDateTimeString();

    openTaskSheet($plants)
        ->set('editTaskTitle', 'Water the plants twice')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotDispatched('nav-counts-changed');

    $fresh = $plants->fresh();

    expect([$fresh->title, $fresh->done, $fresh->completed_at->toDateTimeString()])->toBe(['Water the plants twice', true, $completedAt]);
});

it('moves a weekly task\'s weekday with its date and drops it for once', function () {
    $bag = taskSheetRow('Put out the PMD bag');

    openTaskSheet($bag)
        ->set('editDueDate', '2026-10-30')
        ->call('save')
        ->assertHasNoErrors();

    expect($bag->fresh()->repeat_weekday)->toBe(5);

    openTaskSheet($bag)
        ->assertSet('repeat', 'weekly')
        ->set('repeat', 'once')
        ->call('save')
        ->assertHasNoErrors();

    expect($bag->fresh()->repeat_weekday)->toBeNull()
        ->and($bag->fresh()->due_date->toDateString())->toBe('2026-10-30');
});

// Bad input

it('rejects a bad value, saves nothing and stays open', function (string $field, string $value) {
    $book = taskSheetRow('Return library book');
    Task::query()->update(['updated_at' => '2026-10-01 08:00:00']);
    $before = taskSheetSnapshot();

    $component = openTaskSheet($book)
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field)
        ->assertNotDispatched('toast')
        ->assertNotDispatched('task-changed')
        ->assertNotDispatched('close-sheet')
        ->assertSet('taskId', $book->id);

    expect(taskSheetSnapshot())->toBe($before)
        ->and($component->errors()->first($field))->not->toBe('')
        ->and(taskSheetHtml($component)->documentElement->textContent)->toContain($component->errors()->first($field));
})->with([
    'an empty title' => ['editTaskTitle', ''],
    'a title of spaces' => ['editTaskTitle', '   '],
    'a 256-character title' => ['editTaskTitle', str_repeat('a', 256)],
    'priority urgent' => ['priority', 'urgent'],
    'priority HIGH' => ['priority', 'HIGH'],
    'priority medium' => ['priority', 'medium'],
    'tag work' => ['tag', 'work'],
    'tag Home' => ['tag', 'Home'],
    'an empty tag' => ['tag', ''],
    'repeat daily' => ['repeat', 'daily'],
    'an empty repeat' => ['repeat', ''],
    'date someday' => ['editDueDate', 'someday'],
    'date 2026-02-31' => ['editDueDate', '2026-02-31'],
    'date 29/10/2026' => ['editDueDate', '29/10/2026'],
    'date with a time' => ['editDueDate', '2026-10-29 12:00'],
]);

it('shows the errors under the fields, with their names in plain words', function () {
    $component = openTaskSheet(taskSheetRow('Return library book'))
        ->set('editTaskTitle', '')
        ->set('editDueDate', 'someday')
        ->call('save');
    $page = taskSheetHtml($component);
    $errorOf = function (string $field) use ($page) {
        $input = $page->getElementById($field);

        expect($input->getAttribute('aria-invalid'))->toBe('true');

        return trim($page->getElementById($input->getAttribute('aria-describedby'))->textContent);
    };

    expect($errorOf('editTaskTitle'))->toBe('The task field is required.')
        ->and($errorOf('editDueDate'))->toBe('The due date field must match the format Y-m-d.');
});

// Closing

it('forgets the task, the changes and the errors when the sheet closes', function () {
    $book = taskSheetRow('Return library book');

    $component = openTaskSheet($book)
        ->set('editTaskTitle', '')
        ->set('priority', 'high')
        ->call('save')
        ->assertHasErrors('editTaskTitle')
        ->call('clear')
        ->assertHasNoErrors()
        ->assertSet('taskId', null)
        ->assertSet('editTaskTitle', '')
        ->assertSet('editDueDate', '')
        ->assertSet('priority', null)
        ->assertSet('tag', '')
        ->assertSet('repeat', '');

    expect(taskSheetHtml($component)->querySelector('dialog#sheet-task form'))->toBeNull()
        ->and($book->fresh()->title)->toBe('Return library book')
        ->and($book->fresh()->priority)->toBe(TaskPriority::Med);
});

it('shows the next task\'s values without the last one\'s errors', function () {
    $component = openTaskSheet(taskSheetRow('Return library book'))
        ->set('editTaskTitle', '')
        ->call('save')
        ->assertHasErrors('editTaskTitle')
        ->dispatch('show-task', id: taskSheetRow('Finish cloud lab report')->id)
        ->assertHasNoErrors()
        ->assertSet('editTaskTitle', 'Finish cloud lab report')
        ->assertSet('editDueDate', '2026-10-30')
        ->assertSet('priority', 'high')
        ->assertSet('tag', 'school')
        ->assertSet('repeat', 'once');

    expect(taskSheetHtml($component)->getElementById('editTaskTitle')->hasAttribute('aria-invalid'))->toBeFalse()
        ->and(taskSheetPressed($component)['Due'])->toBe(['Tomorrow']);
});

// Deleting

it('deletes only the task, with a toast, and closes', function () {
    $book = taskSheetRow('Return library book');
    $count = Task::count();

    openTaskSheet($book)
        ->call('deleteTask')
        ->assertDispatched('toast', message: 'Task deleted')
        ->assertDispatched('close-sheet', name: 'task')
        ->assertDispatched('task-changed', id: $book->id)
        ->assertDispatched('nav-counts-changed')
        ->assertSet('taskId', null);

    expect(Task::find($book->id))->toBeNull()
        ->and(Task::count())->toBe($count - 1)
        ->and($this->user->tasks()->where('title', 'like', 'Return library%')->exists())->toBeFalse();
});

// Other users and stale sheets

it('gives a 404 and does not open for another user\'s task', function () {
    Livewire::test(TaskSheet::class)
        ->dispatch('show-task', id: Task::factory()->create()->id)
        ->assertNotFound()
        ->assertNotDispatched('open-sheet');
});

it('locks the task id against the browser', function () {
    expect(fn () => openTaskSheet(taskSheetRow('Return library book'))->set('taskId', Task::factory()->create()->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('does nothing when no task is loaded', function (string $action) {
    $before = taskSheetSnapshot();

    Livewire::test(TaskSheet::class)
        ->call($action)
        ->assertHasNoErrors()
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet')
        ->assertNotDispatched('task-changed')
        ->assertNotDispatched('nav-counts-changed');

    expect(taskSheetSnapshot())->toBe($before);
})->with(['save', 'deleteTask']);

it('does nothing on a second delete', function () {
    $count = Task::count();

    openTaskSheet(taskSheetRow('Return library book'))
        ->call('deleteTask')
        ->call('deleteTask')
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet')
        ->assertNotDispatched('task-changed')
        ->assertNotDispatched('nav-counts-changed');

    expect(Task::count())->toBe($count - 1);
});

it('does not bring back a task deleted while the sheet was open', function () {
    $book = taskSheetRow('Return library book');
    $component = openTaskSheet($book);

    $book->delete();

    $component->set('editTaskTitle', 'Return library books')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotDispatched('toast')
        ->assertNotDispatched('close-sheet')
        ->assertNotDispatched('task-changed');

    expect(Task::find($book->id))->toBeNull()
        ->and($this->user->tasks()->where('title', 'like', 'Return library%')->exists())->toBeFalse()
        ->and(taskSheetHtml($component)->querySelector('dialog#sheet-task form'))->toBeNull();
});

it('has no public property for done, completed_at, rolled_over, the weekday or the owner', function () {
    $properties = array_map(
        fn (ReflectionProperty $property) => $property->getName(),
        array_filter(
            (new ReflectionClass(TaskSheet::class))->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property) => $property->class === TaskSheet::class,
        ),
    );

    expect(array_values($properties))->toBe(['taskId', 'editTaskTitle', 'editDueDate', 'priority', 'tag', 'repeat']);
});

// Markup

it('shows a title with markup as text, on To-do, on Home and in the sheet', function () {
    $raw = '<img src=x onerror=alert(1)>';
    $task = Task::factory()->for($this->user)->create(['title' => $raw, 'due_date' => today()]);
    $component = openTaskSheet($task)->assertSet('editTaskTitle', $raw);

    foreach (['/todo', '/'] as $uri) {
        $page = HTMLDocument::createFromString($this->get($uri)->assertOk()->getContent(), LIBXML_NOERROR);
        $button = $page->getElementById("task-open-{$task->id}");

        expect($page->querySelector('img'))->toBeNull()
            ->and($button->firstElementChild->textContent)->toBe($raw)
            ->and($button->getAttribute('x-on:click'))->toBe("\$dispatch('show-task', { id: {$task->id} })");
    }

    expect(taskSheetHtml($component)->querySelector('img'))->toBeNull();
});

it('shares no id with the add-task sheet', function () {
    $ids = fn (Testable $component) => array_map(
        fn (Element $element) => $element->getAttribute('id'),
        iterator_to_array(taskSheetHtml($component)->querySelectorAll('[id]')),
    );

    // With every field in error, so the error messages' ids count too.
    $edit = $ids(openTaskSheet(taskSheetRow('Return library book'))
        ->set('editTaskTitle', '')
        ->set('editDueDate', 'someday')
        ->set('priority', 'urgent')
        ->set('tag', '')
        ->set('repeat', '')
        ->call('save')
        ->assertHasErrors(['editTaskTitle', 'editDueDate', 'priority', 'tag', 'repeat']));
    $add = $ids(Livewire::test(AddTask::class)
        ->set('due', 'someday')
        ->set('priority', 'urgent')
        ->set('tag', '')
        ->set('repeat', '')
        ->call('save')
        ->assertHasErrors(['taskTitle', 'due', 'priority', 'tag', 'repeat']));

    expect($edit)->toContain('sheet-task', 'editTaskTitle', 'editTaskTitle-error', 'edit-task-due', 'editDueDate', 'editDueDate-error', 'edit-task-priority', 'edit-task-tag', 'edit-task-repeat')
        ->and($add)->toContain('sheet-add-task', 'taskTitle', 'taskTitle-error', 'add-task-due', 'add-task-priority', 'add-task-tag', 'add-task-repeat')
        ->and(array_intersect($edit, $add))->toBe([]);
});
