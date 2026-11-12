<?php

use App\Actions\AddTag;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\Todo;
use App\Livewire\Sheets\AddTask;
use App\Livewire\Sheets\Tags;
use App\Livewire\Sheets\TaskSheet;
use App\Models\CustomTag;
use App\Models\Task;
use App\Models\User;
use App\Parsers\TaskParser;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function customTagsHtml(Testable $component): HTMLDocument
{
    return HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
}

/**
 * The tag chips (the toggles, not the +) in the chip row the selector finds.
 *
 * @return list<string>
 */
function customTagChips(Testable $component, string $row): array
{
    return array_map(
        fn (Element $chip) => trim($chip->textContent),
        iterator_to_array(customTagsHtml($component)->querySelectorAll("{$row} button[aria-pressed]")),
    );
}

/**
 * The tags listed under "Added by you" in the Your tags sheet, with their remove buttons' labels.
 *
 * @return list<array{string, string}>
 */
function ownTagRows(Testable $component): array
{
    return array_map(
        fn (Element $row) => [trim($row->querySelector('span')->textContent), $row->querySelector('button')->getAttribute('aria-label')],
        iterator_to_array(customTagsHtml($component)->querySelectorAll('ul[aria-labelledby="tags-own"] li')),
    );
}

function editTaskSheet(Task $task): Testable
{
    return Livewire::test(TaskSheet::class)->call('show', $task->id);
}

dataset('tag chip rows', [
    'the to-do filter' => [fn () => Livewire::test(Todo::class), '[role="group"][aria-label="Filter by tag"]', ['All']],
    'the add-task sheet' => [fn () => Livewire::test(AddTask::class), '[role="group"][aria-labelledby="add-task-tag"]', []],
    'the edit-task sheet' => [fn () => editTaskSheet(Task::factory()->for($this->user)->create()), '[role="group"][aria-labelledby="edit-task-tag"]', []],
]);

it('lists the built-in tags, then the user\'s own in the order they were added', function () {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    CustomTag::factory()->for($this->user)->create(['name' => 'admin']);
    CustomTag::factory()->create(['name' => 'theirs']);

    expect($this->user->tagNames())->toBe(['school', 'home', 'internship', 'gym', 'admin'])
        ->and(User::factory()->create()->tagNames())->toBe(['school', 'home', 'internship']);
});

it('adds a tag as one lower-case word', function (string $typed, string $name) {
    $tag = app(AddTag::class)->handle($this->user, $typed);

    expect([$tag->name, $tag->user_id])->toBe([$name, $this->user->id])
        ->and(app(AddTag::class)->message($tag))->toBe("Added the {$name} tag");
})->with([
    'as typed' => ['gym', 'gym'],
    'capitals' => ['GYM', 'gym'],
    'a leading #' => ['#gym', 'gym'],
    'spaces become hyphens' => ['  Side   project ', 'side-project'],
    'an underscore' => ['side_project', 'side_project'],
    'accents and digits' => ['Équipe2', 'équipe2'],
]);

it('refuses a tag that is blank, too long, not one word, taken, or all', function (string $name, string $message) {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);

    expect(fn () => app(AddTag::class)->handle($this->user, $name))->toThrow(ValidationException::class, $message)
        ->and($this->user->customTags()->pluck('name')->all())->toBe(['gym']);
})->with([
    'blank' => ['   ', 'The tag field is required.'],
    'only a #' => ['#', 'The tag field is required.'],
    'too long' => [str_repeat('a', 21), 'The tag field must not be greater than 20 characters.'],
    'punctuation' => ['gym!', 'A tag can only hold letters, numbers, - and _.'],
    'a built-in tag' => ['School', 'You already have the school tag.'],
    'one of the user\'s own' => ['#GYM', 'You already have the gym tag.'],
    'the filter for every tag' => ['All', 'All is the filter for every tag. Pick another name.'],
]);

it('takes a tag another user already has, and 20 characters', function () {
    CustomTag::factory()->create(['name' => 'gym']);

    app(AddTag::class)->handle($this->user, 'gym');
    app(AddTag::class)->handle($this->user, str_repeat('b', 20));

    expect($this->user->customTags()->count())->toBe(2);
});

it('adds a tag from the sheet and keeps it open with an empty field', function () {
    $component = Livewire::test(Tags::class)
        ->set('tag', 'Gym')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('tag', '')
        ->assertSet('status', 'Added the gym tag')
        ->assertNotDispatched('toast')
        ->assertDispatched('tags-changed')
        ->assertDispatched('tag-added', name: 'gym')
        ->assertNotDispatched('close-sheet');

    expect(ownTagRows($component))->toBe([['gym', 'Remove the gym tag']])
        ->and(customTagsHtml($component)->querySelector('dialog#sheet-tags [role="status"]')->textContent)->toBe('Added the gym tag');
});

it('shows why a tag was refused under the field and adds nothing', function () {
    $component = Livewire::test(Tags::class)
        ->set('tag', 'home')
        ->call('add')
        ->assertHasErrors(['tag'])
        ->assertSet('status', '')
        ->assertNotDispatched('tags-changed');

    expect(customTagsHtml($component)->getElementById('tag')->getAttribute('aria-invalid'))->toBe('true')
        ->and(trim(customTagsHtml($component)->getElementById('tag-error')->textContent))->toBe('You already have the home tag.')
        ->and(CustomTag::count())->toBe(0);
});

it('clears the field, its error and the status when the sheet closes', function () {
    Livewire::test(Tags::class)
        ->set('tag', 'gym')
        ->call('add')
        ->set('tag', 'home')
        ->call('add')
        ->assertHasErrors(['tag'])
        ->call('resetForm')
        ->assertHasNoErrors()
        ->assertSet('tag', '')
        ->assertSet('status', '');
});

it('says which tags are always there and lists only the user\'s own, oldest first', function () {
    $component = Livewire::test(Tags::class);
    $page = customTagsHtml($component);

    expect($page->querySelector('dialog#sheet-tags h2')->textContent)->toBe('Your tags')
        ->and($page->querySelector('dialog#sheet-tags')->textContent)->toContain('school, home and internship are always there.')
        ->and($page->querySelector('dialog#sheet-tags [role="status"]')->textContent)->toBe('')
        ->and($page->getElementById('tags-own'))->toBeNull();

    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    CustomTag::factory()->for($this->user)->create(['name' => 'admin']);
    CustomTag::factory()->create(['name' => 'theirs']);

    expect(ownTagRows($component->call('$refresh')))->toBe([['gym', 'Remove the gym tag'], ['admin', 'Remove the admin tag']]);
});

it('removes a tag and moves its tasks to home', function (int $tasks, string $status) {
    $tag = CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    Task::factory()->count($tasks)->for($this->user)->create(['tag' => 'gym']);
    $school = Task::factory()->for($this->user)->create(['tag' => 'school']);
    $theirs = Task::factory()->create(['tag' => 'gym']);

    $component = Livewire::test(Tags::class)
        ->call('remove', $tag->id)
        ->assertSet('status', $status)
        ->assertDispatched('tags-changed');

    $this->assertModelMissing($tag);

    expect($this->user->tasks()->where('tag', 'home')->count())->toBe($tasks)
        ->and($school->fresh()->tag)->toBe('school')
        ->and($theirs->fresh()->tag)->toBe('gym')
        ->and(ownTagRows($component))->toBe([]);
})->with([
    'no tasks' => [0, 'Removed the gym tag'],
    'one task' => [1, 'Removed the gym tag · 1 task moved to home'],
    'three tasks' => [3, 'Removed the gym tag · 3 tasks moved to home'],
]);

it('leaves another user\'s tag and a tag that is already gone alone', function () {
    $theirs = CustomTag::factory()->create(['name' => 'gym']);

    Livewire::test(Tags::class)
        ->call('remove', $theirs->id)
        ->call('remove', $theirs->id + 1000)
        ->assertSet('status', '')
        ->assertNotDispatched('tags-changed');

    $this->assertModelExists($theirs);
});

it('ends each tag chip row with a + that opens the Your tags sheet', function (Testable $component, string $row) {
    $button = customTagsHtml($component)->querySelector("{$row} > button:last-child");

    expect($button->getAttribute('aria-label'))->toBe('Add or remove tags')
        ->and($button->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($button->hasAttribute('aria-pressed'))->toBeFalse()
        ->and($button->getAttribute('x-on:click'))->toBe("\$dispatch('open-sheet', { name: 'tags' })");
})->with('tag chip rows');

it('shows the user\'s own tags after the built-in ones in each tag chip row', function (Testable $component, string $row, array $first) {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    CustomTag::factory()->create(['name' => 'theirs']);

    expect(customTagChips($component->call('$refresh'), $row))->toBe([...$first, 'school', 'home', 'internship', 'gym']);
})->with('tag chip rows');

it('picks up a tag added elsewhere when tags change', function (Testable $component, string $row) {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);

    expect(customTagChips($component->dispatch('tags-changed'), $row))->toContain('gym');
})->with('tag chip rows');

it('sets a tag of the user\'s own from its chip', function (Testable $component, string $row, array $first) {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);

    $chips = customTagsHtml($component->call('$refresh'))->querySelectorAll("{$row} button[aria-pressed]");
    $property = $first === [] ? 'tag' : 'tagFilter';

    expect($chips->item(count($first) + 3)->getAttribute('wire:click'))->toBe("\$set('{$property}', 'gym')");
})->with('tag chip rows');

it('picks a tag added on top of an open add-task or edit-task sheet', function (string $component, string $name) {
    $wrapper = customTagsHtml(Livewire::test($component))->querySelector('div:has(> dialog#sheet-'.$name.')');

    expect($wrapper->getAttribute('x-on:tag-added.window'))->toBe("\$el.querySelector('dialog').open && \$wire.set('tag', \$event.detail.name)");
})->with([
    'add-task' => [AddTask::class, 'add-task'],
    'edit-task' => [TaskSheet::class, 'task'],
]);

it('adds a task with a tag of the user\'s own', function () {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);

    Livewire::test(AddTask::class)
        ->set('taskTitle', 'Leg day')
        ->set('tag', 'gym')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->tasks()->sole()->tag)->toBe('gym');
});

it('saves an edited task with a tag of the user\'s own', function () {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    $task = Task::factory()->for($this->user)->create(['tag' => 'home']);

    editTaskSheet($task)->set('tag', 'gym')->call('save')->assertHasNoErrors();

    expect($task->fresh()->tag)->toBe('gym');
});

it('opens a task with a tag of the user\'s own with that chip pressed', function () {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    $task = Task::factory()->for($this->user)->create(['tag' => 'gym']);

    $pressed = customTagsHtml(editTaskSheet($task))->querySelector('[aria-labelledby="edit-task-tag"] button[aria-pressed="true"]');

    expect($pressed->textContent)->toBe('gym');
});

it('refuses a tag that is not one of the user\'s', function () {
    CustomTag::factory()->create(['name' => 'theirs']);
    $task = Task::factory()->for($this->user)->create(['tag' => 'home']);

    Livewire::test(AddTask::class)->set('taskTitle', 'Leg day')->set('tag', 'theirs')->call('save')->assertHasErrors(['tag' => 'in']);
    editTaskSheet($task)->set('tag', 'theirs')->call('save')->assertHasErrors(['tag' => 'in']);

    expect($this->user->tasks()->count())->toBe(1)
        ->and($task->fresh()->tag)->toBe('home');
});

it('falls back to home when the picked tag is removed, and keeps a tag that is still there', function () {
    $gym = CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    CustomTag::factory()->for($this->user)->create(['name' => 'admin']);
    $task = Task::factory()->for($this->user)->create(['tag' => 'school']);

    $keptAdd = Livewire::test(AddTask::class)->set('tag', 'admin');
    $removedAdd = Livewire::test(AddTask::class)->set('tag', 'gym');
    $keptEdit = editTaskSheet($task)->set('tag', 'admin');
    $removedEdit = editTaskSheet($task)->set('tag', 'gym');
    $closedEdit = Livewire::test(TaskSheet::class);

    $gym->delete();

    $keptAdd->dispatch('tags-changed')->assertSet('tag', 'admin');
    $removedAdd->dispatch('tags-changed')->assertSet('tag', 'home');
    $keptEdit->dispatch('tags-changed')->assertSet('tag', 'admin');
    $removedEdit->dispatch('tags-changed')->assertSet('tag', 'home');
    $closedEdit->dispatch('tags-changed')->assertSet('tag', '');
});

it('filters To-do by a tag of the user\'s own, and shows every tag once it is removed', function () {
    $gym = CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    Task::factory()->for($this->user)->create(['title' => 'Leg day', 'tag' => 'gym', 'due_date' => today()]);
    Task::factory()->for($this->user)->create(['title' => 'Essay draft', 'tag' => 'school', 'due_date' => today()]);

    $component = Livewire::test(Todo::class)
        ->set('tagFilter', 'gym')
        ->assertSee('Leg day')
        ->assertDontSee('Essay draft');

    $gym->delete();

    $component->dispatch('tags-changed')->assertSee('Leg day')->assertSee('Essay draft');

    expect(customTagsHtml($component)->querySelector('[aria-label="Filter by tag"] button[aria-pressed="true"]')->textContent)->toBe('All');
});

it('shows a tag of the user\'s own on the To-do row and Home\'s Today card', function () {
    $task = Task::factory()->for($this->user)->create(['title' => 'Leg day', 'tag' => 'gym', 'due_date' => today()]);

    Livewire::test(Todo::class)->assertSeeHtml('<span class="rounded-full bg-white/9 px-2 py-px text-cream">gym</span>');

    expect($task->homeMeta())->toBe('Gym · due today');
});

it('reads a tag of the user\'s own from the quick add', function (string $line, string $title, string $tag) {
    CustomTag::factory()->for($this->user)->create(['name' => 'gym']);
    CustomTag::factory()->for($this->user)->create(['name' => 'homework']);

    Livewire::test(Todo::class)->set('quickTask', $line)->call('add');

    $task = $this->user->tasks()->sole();

    expect([$task->title, $task->tag])->toBe([$title, $tag]);
})->with([
    'its name' => ['Leg day #gym', 'Leg day', 'gym'],
    'in capitals' => ['Leg day #GYM', 'Leg day', 'gym'],
    'a longer tag wins over one it starts with' => ['#homework maths', 'maths', 'homework'],
    'a built-in tag still reads' => ['Essay #school', 'Essay', 'school'],
]);

it('reads only the tags it is given', function (?array $tags, string $line, string $title, string $tag) {
    $parsed = (new TaskParser)->parse($line, $tags);

    expect([$parsed->title, $parsed->tag])->toBe([$title, $tag]);
})->with([
    'a tag of the user\'s own' => [['school', 'home', 'internship', 'gym'], 'Leg day #gym', 'Leg day', 'gym'],
    'a hyphen in the tag' => [['school', 'home', 'internship', 'side-project'], 'Draft #side-project', 'Draft', 'side-project'],
    'an unknown tag stays in the title' => [['school', 'home', 'internship', 'gym'], '#work Report', '#work Report', 'home'],
    'home still reads in #homework without a homework tag' => [['school', 'home', 'internship', 'gym'], '#homework maths', 'work maths', 'home'],
    'the built-in tags by default' => [null, 'Leg day #gym', 'Leg day #gym', 'home'],
]);

it('refreshes To-do and Home when tags change', function (string $component) {
    expect(Livewire::test($component)->effects['listeners'])->toContain('tags-changed');
})->with([Todo::class, Home::class]);
