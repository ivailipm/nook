<?php

use App\Enums\TaskPriority;
use App\Enums\TaskTag;
use App\Livewire\Pages\Todo;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\TaskSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function todoHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * Each group card as "Title · count", with its rows as "Title: meta | meta" and " (done)" after a ticked one.
 *
 * @return array<string, list<string>>
 */
function todoCards(Testable $component): array
{
    $cards = [];

    foreach (todoHtml($component)->querySelectorAll('section') as $card) {
        $heading = $card->querySelector('h2');

        if ($heading === null) {
            continue;
        }

        $rows = array_map(function (Element $checkbox) {
            $text = $checkbox->nextElementSibling;
            $meta = [];

            for ($item = $text->lastElementChild->firstElementChild; $item !== null; $item = $item->nextElementSibling) {
                $meta[] = preg_replace('/\s+/', ' ', trim($item->textContent));
            }

            $row = trim($text->firstElementChild->textContent).': '.implode(' | ', $meta);

            return $checkbox->getAttribute('aria-pressed') === 'true' ? "{$row} (done)" : $row;
        }, iterator_to_array($card->querySelectorAll('button[id^="task-check-"]')));

        $cards[trim($heading->textContent).' · '.trim($heading->nextElementSibling->textContent)] = $rows;
    }

    return $cards;
}

function todoCheckbox(Testable $component, Task $task): ?Element
{
    return todoHtml($component)->getElementById("task-check-{$task->id}");
}

/**
 * The filter chips, with the pressed ones marked.
 *
 * @return list<string>
 */
function todoFilterChips(Testable $component): array
{
    return array_map(
        fn (Element $chip) => trim($chip->textContent).($chip->getAttribute('aria-pressed') === 'true' ? ' (pressed)' : ''),
        iterator_to_array(todoHtml($component)->querySelectorAll('[role="group"][aria-label="Filter by tag"] button')),
    );
}

function todoNudgeToggle(Testable $component): Element
{
    return todoHtml($component)->querySelector('button[aria-label="Morning nudge"]');
}

it('shows the seeded tasks in their groups', function () {
    app(TaskSeeder::class)->run($this->user);

    expect(todoCards(Livewire::test(Todo::class)))->toBe([
        'Overdue · 1 task' => ['Return library book: Medium | overdue · was yesterday | school'],
        'Today · 2 tasks' => [
            'Send CV to two companies: High | due today | internship',
            'Put out the PMD bag: due today | every Thursday | home',
        ],
        'Upcoming · 3 tasks' => [
            'Finish cloud lab report: High | due tomorrow | school',
            'Read deep learning chapter 5: Low | due Mon | school',
            'Take out the trash: due Tue | every Tuesday | home',
        ],
        'Done · 1 task' => ['Water the plants: Low | due today | home (done)'],
    ]);
});

it('colours the group titles and strikes through a done title', function () {
    app(TaskSeeder::class)->run($this->user);

    $page = todoHtml(Livewire::test(Todo::class));
    $headings = iterator_to_array($page->querySelectorAll('section h2'));
    $titleOf = fn (string $title) => array_values(array_filter(
        iterator_to_array($page->querySelectorAll('section button[id^="task-check-"]')),
        fn (Element $checkbox) => trim($checkbox->nextElementSibling->firstElementChild->textContent) === $title,
    ))[0]->nextElementSibling->firstElementChild;

    expect(array_map(fn (Element $heading) => $heading->getAttribute('class'), $headings))->toBe([
        'font-display text-[19px] font-semibold text-overdue',
        'font-display text-[19px] font-semibold',
        'font-display text-[19px] font-semibold',
        'font-display text-[19px] font-semibold text-cream-muted',
    ])
        ->and($titleOf('Water the plants')->getAttribute('class'))->toBe('text-[15px] font-semibold in-data-done:line-through in-data-done:opacity-55')
        ->and($titleOf('Return library book')->getAttribute('class'))->toBe('text-[15px] font-semibold in-data-done:line-through in-data-done:opacity-55');
});

it('marks only a done row with data-done and gives its checkbox both labels', function () {
    app(TaskSeeder::class)->run($this->user);

    $component = Livewire::test(Todo::class);
    $done = todoCheckbox($component, $this->user->tasks()->where('title', 'Water the plants')->sole());
    $open = todoCheckbox($component, $this->user->tasks()->where('title', 'Return library book')->sole());
    $labels = fn (Element $checkbox) => array_map(fn (string $name) => $checkbox->getAttribute($name), ['aria-pressed', 'aria-label', 'data-label', 'data-checked-label']);

    expect(array_map(fn (Element $row) => $row->getAttribute('wire:key'), iterator_to_array(todoHtml($component)->querySelectorAll('[data-done]'))))
        ->toBe([$done->parentElement->getAttribute('wire:key')])
        ->and($done->parentElement->getAttribute('wire:key'))->toStartWith('task-')
        ->and($open->parentElement->hasAttribute('data-done'))->toBeFalse()
        ->and($labels($done))->toBe(['true', 'Mark not done: Water the plants', 'Complete: Water the plants', 'Mark not done: Water the plants'])
        ->and($labels($open))->toBe(['false', 'Complete: Return library book', 'Complete: Return library book', 'Mark not done: Return library book']);
});

it('colours the due label by its state', function (?int $days, bool $done, ?string $colour) {
    Task::factory()->for($this->user)->create([
        'due_date' => $days === null ? null : today()->addDays($days),
        'done' => $done,
        'completed_at' => $done ? now() : null,
    ]);

    $due = todoHtml(Livewire::test(Todo::class))->querySelector('section button[id^="task-check-"]')->nextElementSibling->lastElementChild->firstElementChild;
    $expected = $colour === null ? 'font-semibold' : "font-semibold {$colour}";

    expect($due->getAttribute('class'))->toBe($expected);
})->with([
    'open, overdue' => [-1, false, 'text-overdue'],
    'open, today' => [0, false, 'text-due-today'],
    'open, tomorrow' => [1, false, null],
    'open, no date' => [null, false, null],
    'done, past' => [-1, true, null],
    'done, today' => [0, true, null],
]);

it('renders the quick add, the hint and the page\'s layout', function () {
    $page = todoHtml(Livewire::test(Todo::class));
    $input = $page->getElementById('quick-add-task');
    $submit = $input->closest('form')->querySelector('button[type="submit"]');
    $label = $input->closest('form')->querySelector('label[for="quick-add-task"]');

    expect([$input->getAttribute('wire:model'), $input->getAttribute('placeholder'), $input->getAttribute('autocomplete'), $input->getAttribute('maxlength')])
        ->toBe(['quickTask', 'Email the prof tomorrow !high #school', 'off', '255'])
        ->and($input->hasAttribute('aria-invalid'))->toBeFalse()
        ->and($input->closest('form')->getAttribute('wire:submit'))->toBe('add')
        ->and(trim($label->textContent))->toBe('Add a task')
        ->and($label->classList->contains('sr-only'))->toBeTrue()
        ->and($submit->classList->contains('bg-accent'))->toBeTrue()
        ->and($submit->classList->contains('text-on-accent'))->toBeTrue()
        ->and(trim($page->querySelector('h1')->textContent))->toBe('Small things')
        ->and($page->documentElement->textContent)->toContain('today / tomorrow / a weekday · !high !low · #school #home #internship · "every tue" repeats')
        ->and($page->querySelector('.grid.grid-cols-1.items-start.gap-3\\.5.wide\\:grid-cols-2'))->not->toBeNull();
});

it('adds a task from a quick-add line', function (string $line, string $title, ?string $due, ?TaskPriority $priority, TaskTag $tag, ?int $repeat, string $toast) {
    Livewire::test(Todo::class)
        ->set('quickTask', $line)
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('quickTask', '')
        ->assertDispatched('toast', message: $toast);

    $task = $this->user->tasks()->sole();

    expect([$task->title, $task->due_date?->toDateString(), $task->priority, $task->tag, $task->repeat_weekday, $task->done])
        ->toBe([$title, $due, $priority, $tag, $repeat, false]);
})->with([
    'tomorrow, high and a tag' => ['Email the prof tomorrow !high #school', 'Email the prof', '2026-10-30', TaskPriority::High, TaskTag::School, null, 'Task added · due tomorrow'],
    'every tue, keeping "monitor"' => ['Clean the monitor !medium every tue #school', 'Clean the monitor', '2026-11-03', TaskPriority::Med, TaskTag::School, 2, 'Task added · due Tue'],
    'every thu, on a Thursday' => ['Put out the bins every thu', 'Put out the bins', '2026-10-29', null, TaskTag::Home, 4, 'Task added · due today'],
    'today and low' => ['water plants today !low', 'water plants', '2026-10-29', TaskPriority::Low, TaskTag::Home, null, 'Task added · due today'],
    'no date' => ['Buy stamps', 'Buy stamps', null, null, TaskTag::Home, null, 'Task added'],
]);

it('shows a quick-added task in its group', function () {
    $component = Livewire::test(Todo::class)->set('quickTask', 'Email the prof tomorrow !high #school')->call('add');

    expect(todoCards($component))->toBe(['Upcoming · 1 task' => ['Email the prof: High | due tomorrow | school']]);
});

it('adds nothing for a line without a title and keeps the input', function (string $line) {
    Livewire::test(Todo::class)
        ->set('quickTask', $line)
        ->call('add')
        ->assertHasNoErrors()
        ->assertNotDispatched('toast')
        ->assertSet('quickTask', $line);

    expect(Task::count())->toBe(0);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'only a priority and a tag' => '!high #school',
    'only a date' => 'today',
]);

it('validates the line before parsing it', function (string $line) {
    $component = Livewire::test(Todo::class)
        ->set('quickTask', $line)
        ->call('add')
        ->assertHasErrors(['quickTask' => 'max'])
        ->assertNotDispatched('toast')
        ->assertSet('quickTask', $line);

    $page = todoHtml($component);
    $input = $page->getElementById('quick-add-task');

    expect(Task::count())->toBe(0)
        ->and($input->getAttribute('aria-invalid'))->toBe('true')
        ->and($input->getAttribute('aria-describedby'))->toBe('quick-add-task-error')
        ->and(trim($page->getElementById('quick-add-task-error')->textContent))->toBe('The task field must not be greater than 255 characters.');
})->with([
    '256 characters' => str_repeat('a', 256),
    '6,200 spaces, which the parser can\'t squish' => 'a'.str_repeat(' ', 6200).'b',
]);

it('adds a 255-character line', function () {
    Livewire::test(Todo::class)
        ->set('quickTask', str_repeat('a', 255))
        ->call('add')
        ->assertHasNoErrors();

    expect($this->user->tasks()->sole()->title)->toBe(str_repeat('a', 255));
});

it('refuses to tick another user\'s task', function (?int $repeatWeekday) {
    $theirs = Task::factory()->create(['due_date' => today(), 'repeat_weekday' => $repeatWeekday]);

    Livewire::test(Todo::class)
        ->call('toggle', $theirs->id, true)
        ->assertNotFound();

    expect($theirs->fresh()->done)->toBeFalse()
        ->and($theirs->fresh()->rolled_over)->toBeFalse()
        ->and(Task::count())->toBe(1);
})->with(['a one-off' => [null], 'a recurring task' => [4]]);

it('never lists another user\'s tasks', function () {
    Task::factory()->create(['title' => 'Theirs', 'due_date' => today()]);
    Task::factory()->for($this->user)->create(['title' => 'Mine', 'due_date' => today(), 'tag' => TaskTag::Home]);

    expect(todoCards(Livewire::test(Todo::class)))->toBe(['Today · 1 task' => ['Mine: due today | home']]);
});

it('rolls a recurring task over and shows the next one under Upcoming', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Bins', 'tag' => TaskTag::Home, 'due_date' => today(), 'repeat_weekday' => 4]);

    $component = Livewire::test(Todo::class)
        ->call('toggle', $task->id, true)
        ->assertDispatched('toast', message: 'Done. Next one: Thu 5 Nov');

    expect(todoCards($component))->toBe([
        'Upcoming · 1 task' => ['Bins: due 5 Nov | every Thursday | home'],
        'Done · 1 task' => ['Bins: due today | every Thursday | home (done)'],
    ]);
});

it('ticks a one-off without a toast, and unticking returns it to its group', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Stamps', 'tag' => TaskTag::Home, 'due_date' => today()]);

    $component = Livewire::test(Todo::class)
        ->call('toggle', $task->id, true)
        ->assertNotDispatched('toast');

    expect(todoCards($component))->toBe(['Done · 1 task' => ['Stamps: due today | home (done)']]);

    $component->call('toggle', $task->id, false)->assertNotDispatched('toast');

    expect(todoCards($component))->toBe(['Today · 1 task' => ['Stamps: due today | home']]);
});

it('unticks and re-ticks a recurring task without a second copy or toast', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Bins', 'tag' => TaskTag::Home, 'due_date' => today(), 'repeat_weekday' => 4]);

    $component = Livewire::test(Todo::class)->call('toggle', $task->id, true);

    $component->call('toggle', $task->id, false)->assertNotDispatched('toast');
    $component->call('toggle', $task->id, true)->assertNotDispatched('toast');

    expect($this->user->tasks()->count())->toBe(2);
});

it('ticks a recurring task once when the same tick is saved twice', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Bins', 'tag' => TaskTag::Home, 'due_date' => today(), 'repeat_weekday' => 4]);

    $component = Livewire::test(Todo::class)
        ->call('toggle', $task->id, true)
        ->assertDispatched('toast', message: 'Done. Next one: Thu 5 Nov');

    $component->call('toggle', $task->id, true)
        ->assertNotDispatched('toast')
        ->assertDispatched('nav-counts-changed');

    expect($this->user->tasks()->count())->toBe(2)
        ->and($task->fresh()->done)->toBeTrue()
        ->and(todoCards($component))->toBe([
            'Upcoming · 1 task' => ['Bins: due 5 Nov | every Thursday | home'],
            'Done · 1 task' => ['Bins: due today | every Thursday | home (done)'],
        ]);
});

it('leaves an open task alone when it is unticked', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Bins', 'tag' => TaskTag::Home, 'due_date' => today(), 'repeat_weekday' => 4]);
    $before = $task->fresh()->getAttributes();

    $this->travel(1)->minutes();

    $component = Livewire::test(Todo::class)
        ->call('toggle', $task->id, false)
        ->assertNotDispatched('toast');

    expect($task->fresh()->getAttributes())->toBe($before)
        ->and($this->user->tasks()->count())->toBe(1)
        ->and(todoCards($component))->toBe(['Today · 1 task' => ['Bins: due today | every Thursday | home']]);
});

it('names the checkbox by the task and restores its focus by id', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Stamps', 'due_date' => today()]);

    $component = Livewire::test(Todo::class);
    $open = todoCheckbox($component, $task);

    expect($open->getAttribute('aria-label'))->toBe('Complete: Stamps')
        ->and($open->getAttribute('aria-pressed'))->toBe('false')
        ->and($open->getAttribute('x-on:click'))->toBe("const focused = document.activeElement === \$el; \$tick((done) => \$wire.toggle({$task->id}, done)).then(() => focused && document.getElementById('task-check-{$task->id}')?.focus())");

    $component->call('toggle', $task->id, true);
    $done = todoCheckbox($component, $task);

    expect($done->getAttribute('aria-label'))->toBe('Mark not done: Stamps')
        ->and($done->getAttribute('aria-pressed'))->toBe('true')
        ->and($done->getAttribute('x-on:click'))->toBe($open->getAttribute('x-on:click'));
});

it('renders a title as text', function () {
    $task = Task::factory()->for($this->user)->create(['title' => '<img src=x onerror=alert(1)>', 'due_date' => today()]);

    $component = Livewire::test(Todo::class);
    $page = todoHtml($component);
    $checkbox = todoCheckbox($component, $task);

    expect($page->querySelector('img'))->toBeNull()
        ->and(trim($checkbox->nextElementSibling->firstElementChild->textContent))->toBe('<img src=x onerror=alert(1)>')
        ->and($checkbox->getAttribute('aria-label'))->toBe('Complete: <img src=x onerror=alert(1)>')
        ->and($checkbox->getAttribute('x-on:click'))->not->toContain('img')
        ->and($checkbox->getAttribute('data-checked-label'))->toBe('Mark not done: <img src=x onerror=alert(1)>');
});

it('stores a quick-add line\'s markup as typed', function () {
    $component = Livewire::test(Todo::class)->set('quickTask', '<b>bold</b> today')->call('add');

    expect($this->user->tasks()->sole()->title)->toBe('<b>bold</b>')
        ->and(todoHtml($component)->querySelector('section b'))->toBeNull()
        ->and(todoCards($component))->toHaveKey('Today · 1 task');
});

it('filters by tag with the chips', function () {
    app(TaskSeeder::class)->run($this->user);

    $component = Livewire::test(Todo::class);
    $filter = todoHtml($component)->querySelector('[role="group"][aria-label="Filter by tag"]');

    expect(todoFilterChips($component))->toBe(['All (pressed)', 'school', 'home', 'internship'])
        ->and($filter->querySelector('button')->getAttribute('wire:click'))->toBe("\$set('tagFilter', 'All')");

    $component->set('tagFilter', 'school');

    expect(todoFilterChips($component))->toBe(['All', 'school (pressed)', 'home', 'internship'])
        ->and(todoCards($component))->toBe([
            'Overdue · 1 task' => ['Return library book: Medium | overdue · was yesterday | school'],
            'Upcoming · 2 tasks' => [
                'Finish cloud lab report: High | due tomorrow | school',
                'Read deep learning chapter 5: Low | due Mon | school',
            ],
        ]);

    $component->set('tagFilter', 'All');

    expect(todoFilterChips($component))->toBe(['All (pressed)', 'school', 'home', 'internship'])
        ->and(todoCards($component))->toHaveCount(4);
});

it('shows every tag for a filter that isn\'t a tag', function () {
    app(TaskSeeder::class)->run($this->user);

    $component = Livewire::test(Todo::class)->set('tagFilter', 'nonsense');

    expect(todoFilterChips($component))->toBe(['All (pressed)', 'school', 'home', 'internship'])
        ->and(todoCards($component))->toHaveCount(4);
});

it('shows the empty line when there is nothing to show', function () {
    $line = 'nothing here yet. add a small thing';

    $component = Livewire::test(Todo::class)->assertSee($line);

    expect(todoCards($component))->toBe([]);

    Task::factory()->for($this->user)->create(['tag' => TaskTag::Home, 'due_date' => today()]);

    $component->call('$refresh')->assertDontSee($line);
    $component->set('tagFilter', 'internship')->assertSee($line);
});

it('marks each priority with a shape and its word', function (TaskPriority $priority, string $word, string $shape, string $colour) {
    Task::factory()->for($this->user)->create(['priority' => $priority, 'due_date' => today()]);

    $label = todoHtml(Livewire::test(Todo::class))->querySelector('section button[id^="task-check-"]')->nextElementSibling->lastElementChild->firstElementChild;
    $marker = $label->firstElementChild;

    expect(trim($label->textContent))->toBe($word)
        ->and($label->getAttribute('class'))->toBe("inline-flex items-center gap-1 font-bold {$colour}")
        ->and($marker->getAttribute('aria-hidden'))->toBe('true')
        ->and($marker->getAttribute('class'))->toBe("size-2 border-[1.5px] {$shape}");
})->with([
    'high is a square' => [TaskPriority::High, 'High', 'rounded-[2px] border-overdue bg-overdue', 'text-overdue'],
    'medium is a dot' => [TaskPriority::Med, 'Medium', 'rounded-full border-due-today bg-due-today', 'text-due-today'],
    'low is a ring' => [TaskPriority::Low, 'Low', 'rounded-full border-cream-muted', 'text-cream-muted'],
]);

it('shows a task created elsewhere on task-added', function () {
    $component = Livewire::test(Todo::class);

    Task::factory()->for($this->user)->create(['title' => 'From the sheet', 'tag' => TaskTag::Home, 'due_date' => today()]);

    expect(todoCards($component))->toBe([]);

    $component->dispatch('task-added');

    expect(todoCards($component))->toBe(['Today · 1 task' => ['From the sheet: due today | home']]);
});

it('starts with the morning nudge on, on a just-created user', function () {
    $user = User::factory()->create();

    expect($user->morning_nudge)->toBeTrue();

    $this->actingAs($user);

    $toggle = todoNudgeToggle(Livewire::test(Todo::class));

    expect($toggle->getAttribute('aria-pressed'))->toBe('true')
        ->and($toggle->getAttribute('type'))->toBe('button')
        ->and($toggle->getAttribute('wire:click'))->toBe('toggleNudge')
        ->and($toggle->classList->contains('justify-end'))->toBeTrue()
        ->and($toggle->classList->contains('bg-accent'))->toBeTrue();
});

it('shows the nudge card without a title', function () {
    $card = todoNudgeToggle(Livewire::test(Todo::class))->closest('section');

    expect($card->querySelector('h2'))->toBeNull()
        ->and($card->textContent)->toContain('Morning nudge', "A push notification at 08:00 with what's due today.");
});

it('saves the morning nudge when toggled, with a toast', function () {
    $other = User::factory()->create();

    $component = Livewire::test(Todo::class)
        ->call('toggleNudge')
        ->assertDispatched('toast', message: 'Morning nudge off');

    $toggle = todoNudgeToggle($component);

    expect($this->user->fresh()->morning_nudge)->toBeFalse()
        ->and($toggle->getAttribute('aria-pressed'))->toBe('false')
        ->and($toggle->classList->contains('justify-start'))->toBeTrue()
        ->and($toggle->classList->contains('bg-white/20'))->toBeTrue()
        ->and(todoNudgeToggle(Livewire::test(Todo::class))->getAttribute('aria-pressed'))->toBe('false')
        ->and($other->fresh()->morning_nudge)->toBeTrue();

    Livewire::test(Todo::class)
        ->call('toggleNudge')
        ->assertDispatched('toast', message: 'Morning nudge on · 08:00');

    expect($this->user->fresh()->morning_nudge)->toBeTrue()
        ->and(todoNudgeToggle(Livewire::test(Todo::class))->getAttribute('aria-pressed'))->toBe('true');
});

it('reads the morning nudge as on for a row inserted without it', function () {
    $id = DB::table('users')->insertGetId(['name' => 'Existing', 'email' => 'existing@example.com', 'password' => 'secret']);

    expect(User::find($id)->morning_nudge)->toBeTrue();
});

it('makes each row\'s text a button that opens the edit sheet, in every group', function () {
    app(TaskSeeder::class)->run($this->user);

    $page = todoHtml(Livewire::test(Todo::class));

    foreach (['Return library book' => 'Overdue', 'Water the plants' => 'Done'] as $title => $group) {
        $task = $this->user->tasks()->where('title', $title)->sole();
        $button = $page->getElementById("task-open-{$task->id}");

        expect($button->tagName)->toBe('BUTTON')
            ->and(trim($button->closest('section')->querySelector('h2')->textContent))->toBe($group)
            ->and($button->getAttribute('type'))->toBe('button')
            ->and($button->getAttribute('aria-haspopup'))->toBe('dialog')
            ->and($button->getAttribute('x-on:click'))->toBe("\$dispatch('show-task', { id: {$task->id} })")
            ->and($button->hasAttribute('aria-pressed'))->toBeFalse()
            ->and($button->hasAttribute('aria-label'))->toBeFalse()
            ->and($button->previousElementSibling->getAttribute('id'))->toBe("task-check-{$task->id}")
            ->and($button->querySelector('div'))->toBeNull()
            ->and($button->getAttribute('class'))->toBe('flex min-w-0 flex-1 cursor-pointer flex-col gap-1 pt-2.5 text-left break-words')
            ->and($button->classList->contains('cursor-pointer'))->toBeTrue()
            ->and($button->classList->contains('text-left'))->toBeTrue()
            ->and(trim($button->firstElementChild->textContent))->toBe($title)
            ->and($button->lastElementChild->getAttribute('class'))->toBe('flex flex-wrap items-center gap-2 text-[12px] text-cream-muted');
    }
});

it('refreshes on task-changed and gives the saved row its focus back', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Stamps', 'tag' => TaskTag::Home, 'due_date' => today()]);
    $component = Livewire::test(Todo::class);

    $task->update(['due_date' => today()->addDay()]);

    expect(todoCards($component))->toBe(['Today · 1 task' => ['Stamps: due today | home']]);

    $component->dispatch('task-changed', id: $task->id)
        ->assertJs("document.getElementById('task-open-{$task->id}')?.focus()");

    expect(todoCards($component))->toBe(['Upcoming · 1 task' => ['Stamps: due tomorrow | home']]);
});
