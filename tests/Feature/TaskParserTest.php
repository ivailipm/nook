<?php

use App\Enums\TaskPriority;
use App\Parsers\TaskParser;
use Illuminate\Support\Carbon;

it('parses a quick-add line', function (string $line, string $title, ?string $due, ?TaskPriority $priority, string $tag, ?int $repeat) {
    // Thursday 29 October 2026, the prototype's base date.
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $task = (new TaskParser)->parse($line);

    expect($task->title)->toBe($title)
        ->and($task->dueDate?->toDateString())->toBe($due)
        ->and($task->priority)->toBe($priority)
        ->and($task->tag)->toBe($tag)
        ->and($task->repeatWeekday)->toBe($repeat);
})->with([
    // FEATURES.md §4 and the placeholder.
    'the placeholder' => ['Email the prof tomorrow !high #school', 'Email the prof', '2026-10-30', TaskPriority::High, 'school', null],
    'a title only' => ['Water the plants', 'Water the plants', null, null, 'home', null],
    'today' => ['Pay rent today', 'Pay rent', '2026-10-29', null, 'home', null],
    'a weekday' => ['Call mum fri', 'Call mum', '2026-10-30', null, 'home', null],
    '!med' => ['Book dentist !med', 'Book dentist', null, TaskPriority::Med, 'home', null],
    '!low' => ['Book dentist !low', 'Book dentist', null, TaskPriority::Low, 'home', null],
    '#internship' => ['Revise #internship', 'Revise', null, null, 'internship', null],
    '#home' => ['Revise #home', 'Revise', null, null, 'home', null],
    'every tue' => ['Take out the trash every tue', 'Take out the trash', '2026-11-03', null, 'home', 2],

    // Every weekday word.
    'sun' => ['Call mum sun', 'Call mum', '2026-11-01', null, 'home', null],
    'sunday' => ['Call mum sunday', 'Call mum', '2026-11-01', null, 'home', null],
    'mon' => ['Call mum mon', 'Call mum', '2026-11-02', null, 'home', null],
    'monday' => ['Call mum monday', 'Call mum', '2026-11-02', null, 'home', null],
    'tue' => ['Call mum tue', 'Call mum', '2026-11-03', null, 'home', null],
    'tues' => ['Call mum tues', 'Call mum', '2026-11-03', null, 'home', null],
    'tuesday' => ['Call mum tuesday', 'Call mum', '2026-11-03', null, 'home', null],
    'wed' => ['Call mum wed', 'Call mum', '2026-11-04', null, 'home', null],
    'weds' => ['Call mum weds', 'Call mum', '2026-11-04', null, 'home', null],
    'wednesday' => ['Call mum wednesday', 'Call mum', '2026-11-04', null, 'home', null],
    'thu, today\'s weekday' => ['Call mum thu', 'Call mum', '2026-11-05', null, 'home', null],
    'thur, today\'s weekday' => ['Call mum thur', 'Call mum', '2026-11-05', null, 'home', null],
    'thurs, today\'s weekday' => ['Call mum thurs', 'Call mum', '2026-11-05', null, 'home', null],
    'thursday, today\'s weekday' => ['Call mum thursday', 'Call mum', '2026-11-05', null, 'home', null],
    'friday' => ['Call mum friday', 'Call mum', '2026-10-30', null, 'home', null],
    'sat' => ['Call mum sat', 'Call mum', '2026-10-31', null, 'home', null],
    'saturday' => ['Call mum saturday', 'Call mum', '2026-10-31', null, 'home', null],

    // The decisions.md fixes.
    'monitor is not Monday' => ['Clean the monitor', 'Clean the monitor', null, null, 'home', null],
    'sunscreen is not Sunday' => ['Buy sunscreen', 'Buy sunscreen', null, null, 'home', null],
    'wedding is not Wednesday' => ['Plan the wedding', 'Plan the wedding', null, null, 'home', null],
    'friends is not Friday' => ['Friends dinner', 'Friends dinner', null, null, 'home', null],
    'thumbtacks is not Thursday' => ['Thumbtacks for the board', 'Thumbtacks for the board', null, null, 'home', null],
    'saturn is not Saturday' => ['Saturn essay', 'Saturn essay', null, null, 'home', null],
    'a plural is not a weekday' => ['Tuesdays club', 'Tuesdays club', null, null, 'home', null],
    'an accent keeps the word whole' => ['Réviser la politique monétaire', 'Réviser la politique monétaire', null, null, 'home', null],
    'every sunscreen does not repeat' => ['every sunscreen', 'every sunscreen', null, null, 'home', null],
    'every monitor does not repeat' => ['every monitor stand', 'every monitor stand', null, null, 'home', null],
    '!medium leaves nothing behind' => ['Book dentist !medium', 'Book dentist', null, TaskPriority::Med, 'home', null],
    '!medium first' => ['!medium Book dentist', 'Book dentist', null, TaskPriority::Med, 'home', null],
    '!MEDIUM in capitals' => ['Book dentist !MEDIUM', 'Book dentist', null, TaskPriority::Med, 'home', null],

    // The prototype's behaviour that stays.
    'the title is not capitalised' => ['email the prof', 'email the prof', null, null, 'home', null],
    'TODAY in capitals' => ['Pay rent TODAY', 'Pay rent', '2026-10-29', null, 'home', null],
    'FRI in capitals' => ['Call mum FRI', 'Call mum', '2026-10-30', null, 'home', null],
    'tokens in capitals' => ['Report !HIGH #SCHOOL', 'Report', null, TaskPriority::High, 'school', null],
    'an unknown tag stays in the title' => ['#work Report', '#work Report', null, null, 'home', null],
    'an unknown priority stays in the title' => ['Report !urgent', 'Report !urgent', null, null, 'home', null],
    'only the first priority is read' => ['!high !low report', '!low report', null, TaskPriority::High, 'home', null],
    'only the first tag is read' => ['#school #home report', '#home report', null, null, 'school', null],
    'only the first date word is read' => ['today tomorrow call', 'tomorrow call', '2026-10-29', null, 'home', null],
    'only the first date word is read, the other way round' => ['tomorrow today call', 'today call', '2026-10-30', null, 'home', null],
    'only the first weekday is read' => ['mon tue call', 'tue call', '2026-11-02', null, 'home', null],
    'a weekday overrides today' => ['today fri call', 'call', '2026-10-30', null, 'home', null],
    'a weekday overrides today, the other way round' => ['fri today call', 'call', '2026-10-30', null, 'home', null],
    '!highway reads as high' => ['!highway signs', 'way signs', null, TaskPriority::High, 'home', null],
    '#homework reads as home' => ['#homework maths', 'work maths', null, null, 'home', null],
    'a priority joined to a word' => ['Buy milk!low', 'Buy milk', null, TaskPriority::Low, 'home', null],
    'every thu on a Thursday is due today' => ['Put out the PMD bag every thu', 'Put out the PMD bag', '2026-10-29', null, 'home', 4],
    'every Monday first' => ['every Monday gym', 'gym', '2026-11-02', null, 'home', 1],
    'every with extra spaces' => ['Gym every   wed', 'Gym', '2026-11-04', null, 'home', 3],
    'every tues' => ['every tues yoga', 'yoga', '2026-11-03', null, 'home', 2],
    'every sun' => ['every sun walk', 'walk', '2026-11-01', null, 'home', 0],
    'tomorrow overrides the repeat\'s date' => ['Gym every tue tomorrow', 'Gym', '2026-10-30', null, 'home', 2],
    'today overrides the repeat\'s date' => ['Gym every tue today', 'Gym', '2026-10-29', null, 'home', 2],
    'a weekday overrides the repeat\'s date' => ['Gym every tue fri', 'Gym', '2026-10-30', null, 'home', 2],
    'a weekday before the repeat' => ['fri gym every tue', 'gym', '2026-10-30', null, 'home', 2],
    'every day does not repeat' => ['every day stretch', 'every day stretch', null, null, 'home', null],
    'extra whitespace' => ['  Call    mum  ', 'Call mum', null, null, 'home', null],
    'whitespace around a removed word' => ['Call  tomorrow  mum', 'Call mum', '2026-10-30', null, 'home', null],
    'tokens without a title' => ['!high #school', '', null, TaskPriority::High, 'school', null],
    'an empty line' => ['', '', null, null, 'home', null],
    'only spaces' => ['   ', '', null, null, 'home', null],
    'a date word without a title' => ['today', '', '2026-10-29', null, 'home', null],

    // The "iu" flags also match the long s, and that must still find the weekday and the tag.
    'a long s in a repeat' => ['Gym every ſun', 'Gym', '2026-11-01', null, 'home', 0],
    'a long s in a tag' => ['Report #ſchool', 'Report', null, null, 'school', null],
]);

it('reads dates from another day', function (string $line, string $due, ?int $repeat) {
    // Sunday 1 November 2026.
    $this->travelTo(Carbon::parse('2026-11-01 12:00', 'Europe/Brussels'));

    $task = (new TaskParser)->parse($line);

    expect($task->dueDate->toDateString())->toBe($due)
        ->and($task->repeatWeekday)->toBe($repeat);
})->with([
    'the day after a Sunday' => ['Gym mon', '2026-11-02', null],
    'Sunday on a Sunday is a week ahead' => ['Gym sun', '2026-11-08', null],
    'every Sunday on a Sunday is due today' => ['Gym every sun', '2026-11-01', 0],
    'tomorrow on a Sunday' => ['Gym tomorrow', '2026-11-02', null],
]);

it('reads today in Brussels at a late hour', function () {
    $this->travelTo(Carbon::parse('2026-10-29 23:30', 'Europe/Brussels'));

    expect((new TaskParser)->parse('Pay rent today')->dueDate->toDateString())->toBe('2026-10-29');
});
