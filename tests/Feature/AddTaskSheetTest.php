<?php

use App\Enums\TaskPriority;
use App\Livewire\Sheets\AddTask;
use App\Models\Task;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function addTaskHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * Each chip row's label and its chips, with the pressed ones marked.
 *
 * @return array<string, list<string>>
 */
function addTaskChips(Testable $component): array
{
    $page = addTaskHtml($component);
    $rows = [];

    foreach ($page->querySelectorAll('[role="group"]') as $group) {
        $label = trim($page->getElementById($group->getAttribute('aria-labelledby'))->textContent);
        $rows[$label] = array_map(
            fn (Element $chip) => trim($chip->textContent).($chip->getAttribute('aria-pressed') === 'true' ? ' (pressed)' : ''),
            iterator_to_array($group->querySelectorAll('button[aria-pressed]')),
        );
    }

    return $rows;
}

dataset('default chips', ['Today, None, home and Once pressed' => [[
    'Due' => ['Today (pressed)', 'Tomorrow', 'Sun', 'Next week', 'No date'],
    'Priority' => ['High', 'Medium', 'Low', 'None (pressed)'],
    'Tag' => ['school', 'home (pressed)', 'internship'],
    'Repeat' => ['Once (pressed)', 'Every week'],
]]]);

it('renders the sheet with an empty task due today', function (array $chips) {
    $component = Livewire::test(AddTask::class);
    $page = addTaskHtml($component);
    $dialog = $page->querySelector('dialog#sheet-add-task');
    $input = $page->getElementById('taskTitle');
    $submit = $dialog->querySelector('button[type="submit"]');

    expect(trim($page->getElementById($dialog->getAttribute('aria-labelledby'))->textContent))->toBe('New task')
        ->and($dialog->parentElement->getAttribute('x-on:close.capture'))->toBe('$wire.resetForm()')
        ->and($dialog->parentElement->getAttribute('x-on:open-sheet.window'))->toBe("\$event.detail.name === 'add-task' && \$wire.\$refresh()")
        ->and($dialog->querySelector('form')->getAttribute('wire:submit'))->toBe('save')
        ->and(trim($dialog->querySelector('label[for="taskTitle"]')->textContent))->toBe('Task')
        ->and([$input->getAttribute('wire:model'), $input->getAttribute('placeholder'), $input->getAttribute('maxlength'), $input->getAttribute('value')])
        ->toBe(['taskTitle', 'Send CV to Cegeka', '255', null])
        ->and(addTaskChips($component))->toBe($chips)
        ->and(trim($submit->textContent))->toBe('Add task')
        ->and($submit->getAttribute('class'))->toBe('min-h-12 cursor-pointer rounded-button bg-apricot text-[15px] font-bold text-on-apricot');

    $component->assertSet('taskTitle', '')
        ->assertSet('due', 'today')
        ->assertSet('priority', null)
        ->assertSet('tag', 'home')
        ->assertSet('repeat', 'once');
})->with('default chips');

it('names the third due chip after the day three days from today', function () {
    expect(addTaskChips(Livewire::test(AddTask::class))['Due'][2])->toBe('Sun');

    $this->travelTo(Carbon::parse('2026-11-02 12:00', 'Europe/Brussels'));

    expect(addTaskChips(Livewire::test(AddTask::class))['Due'][2])->toBe('Thu');
});

it('presses the chip that was picked', function () {
    $component = Livewire::test(AddTask::class)
        ->set('due', 'weekday')
        ->set('priority', 'med')
        ->set('tag', 'internship')
        ->set('repeat', 'weekly');

    expect(addTaskChips($component))->toBe([
        'Due' => ['Today', 'Tomorrow', 'Sun (pressed)', 'Next week', 'No date'],
        'Priority' => ['High', 'Medium (pressed)', 'Low', 'None'],
        'Tag' => ['school', 'home', 'internship (pressed)'],
        'Repeat' => ['Once', 'Every week (pressed)'],
    ]);
});

it('works out the due date and repeat on the server', function (string $due, string $repeat, ?string $dueDate, ?int $weekday, string $toast) {
    Livewire::test(AddTask::class)
        ->set('taskTitle', 'Send CV')
        ->set('due', $due)
        ->set('repeat', $repeat)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: $toast);

    $task = $this->user->tasks()->sole();

    expect([$task->due_date?->toDateString(), $task->repeat_weekday])->toBe([$dueDate, $weekday]);
})->with([
    'today, once' => ['today', 'once', '2026-10-29', null, 'Task added · due today'],
    'tomorrow, once' => ['tomorrow', 'once', '2026-10-30', null, 'Task added · due tomorrow'],
    'weekday, once' => ['weekday', 'once', '2026-11-01', null, 'Task added · due Sun'],
    'next week, once' => ['next-week', 'once', '2026-11-05', null, 'Task added · due 5 Nov'],
    'no date, once' => ['none', 'once', null, null, 'Task added'],
    'today, weekly' => ['today', 'weekly', '2026-10-29', 4, 'Task added · due today'],
    'tomorrow, weekly' => ['tomorrow', 'weekly', '2026-10-30', 5, 'Task added · due tomorrow'],
    'weekday, weekly' => ['weekday', 'weekly', '2026-11-01', 0, 'Task added · due Sun'],
    'next week, weekly' => ['next-week', 'weekly', '2026-11-05', 4, 'Task added · due 5 Nov'],
    'no date, weekly' => ['none', 'weekly', '2026-10-29', 4, 'Task added · due today'],
]);

it('saves a task for the signed-in user, then closes and resets', function (array $chips) {
    $other = User::factory()->create();

    $component = Livewire::test(AddTask::class)
        ->set('taskTitle', '  Send CV to Cegeka  ')
        ->set('due', 'tomorrow')
        ->set('priority', 'high')
        ->set('tag', 'internship')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('task-added')
        ->assertDispatched('close-sheet', name: 'add-task')
        ->assertSet('taskTitle', '')
        ->assertSet('due', 'today')
        ->assertSet('priority', null)
        ->assertSet('tag', 'home')
        ->assertSet('repeat', 'once');

    $task = $this->user->tasks()->sole();

    expect([$task->title, $task->priority, $task->tag, $task->done, $task->completed_at, $task->rolled_over])
        ->toBe(['Send CV to Cegeka', TaskPriority::High, 'internship', false, null, false])
        ->and($other->tasks()->count())->toBe(0)
        ->and(addTaskChips($component))->toBe($chips);
})->with('default chips');

it('rejects a bad value, adds nothing and keeps the sheet open', function (string $field, string $value) {
    $component = Livewire::test(AddTask::class)
        ->set('taskTitle', 'Send CV')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field)
        ->assertNotDispatched('toast')
        ->assertNotDispatched('task-added')
        ->assertNotDispatched('close-sheet');

    expect(Task::count())->toBe(0)
        ->and($component->errors()->first($field))->not->toBe('')
        ->and(addTaskHtml($component)->documentElement->textContent)->toContain($component->errors()->first($field));
})->with([
    'an empty title' => ['taskTitle', ''],
    'a title of spaces' => ['taskTitle', '   '],
    'a 256-character title' => ['taskTitle', str_repeat('a', 256)],
    'priority urgent' => ['priority', 'urgent'],
    'priority HIGH' => ['priority', 'HIGH'],
    'priority medium' => ['priority', 'medium'],
    'tag work' => ['tag', 'work'],
    'tag Home' => ['tag', 'Home'],
    'an empty tag' => ['tag', ''],
    'due someday' => ['due', 'someday'],
    'an empty due' => ['due', ''],
    'repeat daily' => ['repeat', 'daily'],
    'an empty repeat' => ['repeat', ''],
]);

it('shows the title error under the field', function () {
    $component = Livewire::test(AddTask::class)->call('save');
    $page = addTaskHtml($component);
    $input = $page->getElementById('taskTitle');

    expect($input->getAttribute('aria-invalid'))->toBe('true')
        ->and(trim($page->getElementById($input->getAttribute('aria-describedby'))->textContent))->toBe('The task field is required.');
});

it('saves an empty priority as no priority', function () {
    Livewire::test(AddTask::class)
        ->set('taskTitle', 'Send CV')
        ->set('priority', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->tasks()->sole()->priority)->toBeNull();
});

it('saves a 255-character title', function () {
    Livewire::test(AddTask::class)
        ->set('taskTitle', str_repeat('a', 255))
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->tasks()->sole()->title)->toBe(str_repeat('a', 255));
});

it('has no public property for the repeat weekday', function () {
    $properties = array_map(
        fn (ReflectionProperty $property) => $property->getName(),
        array_filter(
            (new ReflectionClass(AddTask::class))->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property) => $property->class === AddTask::class,
        ),
    );

    expect(array_values($properties))->toBe(['taskTitle', 'due', 'priority', 'tag', 'repeat']);
});

it('goes back to the defaults on resetForm', function (array $chips) {
    $component = Livewire::test(AddTask::class)
        ->set('taskTitle', str_repeat('a', 256))
        ->set('due', 'none')
        ->set('priority', 'high')
        ->set('tag', 'school')
        ->set('repeat', 'weekly')
        ->call('save')
        ->assertHasErrors('taskTitle')
        ->call('resetForm')
        ->assertHasNoErrors()
        ->assertSet('taskTitle', '')
        ->assertSet('due', 'today')
        ->assertSet('priority', null)
        ->assertSet('tag', 'home')
        ->assertSet('repeat', 'once');

    expect(addTaskChips($component))->toBe($chips)
        ->and(addTaskHtml($component)->getElementById('taskTitle')->hasAttribute('aria-invalid'))->toBeFalse();
})->with('default chips');
