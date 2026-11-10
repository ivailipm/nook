<?php

use App\Enums\Section;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;

beforeEach(function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create());
});

dataset('section pages', ['home' => '/', 'groceries' => '/groceries', 'promo board' => '/promos', 'to-do' => '/todo', 'subscriptions' => '/subscriptions']);

function sectionPage(string $uri): HTMLDocument
{
    return HTMLDocument::createFromString(test()->get($uri)->assertOk()->getContent(), LIBXML_NOERROR);
}

function fabButton(string $uri): Element
{
    return sectionPage($uri)->querySelectorAll('nav[aria-label="Main"]')->item(1)->querySelector('button');
}

it('puts the Add something chooser on every section page', function (string $uri) {
    $dialogs = sectionPage($uri)->querySelectorAll('dialog#sheet-add-something');
    $dialog = $dialogs->item(0);
    $tiles = array_map(
        fn (Element $tile) => [$tile->tagName, $tile->getAttribute('href'), preg_replace('/\s+/', ' ', trim($tile->textContent))],
        iterator_to_array($dialog->querySelectorAll('a, button:not([aria-label="Close"])')),
    );

    expect($dialogs)->toHaveCount(1)
        ->and(trim($dialog->querySelector('h2')->textContent))->toBe('Add something')
        ->and($tiles)->toBe([
            ['BUTTON', null, 'Grocery item add to the list'],
            ['BUTTON', null, 'Promo photo or screenshot'],
            ['BUTTON', null, 'Task with a due date'],
            ['BUTTON', null, 'Subscription or a free trial'],
        ]);
})->with('section pages');

it('lets the Grocery item tile close the chooser and open the add-item sheet', function (string $uri) {
    $tile = sectionPage($uri)->querySelector('dialog#sheet-add-something button:not([aria-label="Close"])');

    expect($tile->getAttribute('type'))->toBe('button')
        ->and($tile->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($tile->getAttribute('x-on:click'))->toBe("\$root.close(); \$dispatch('open-sheet', { name: 'add-item' })");
})->with('section pages');

it('lets the Promo tile close the chooser and open the add-promo sheet', function (string $uri) {
    $tile = sectionPage($uri)->querySelectorAll('dialog#sheet-add-something button:not([aria-label="Close"])')->item(1);

    expect(trim($tile->querySelector('span')->textContent))->toBe('Promo')
        ->and($tile->getAttribute('type'))->toBe('button')
        ->and($tile->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($tile->getAttribute('x-on:click'))->toBe("\$root.close(); \$dispatch('open-sheet', { name: 'add-promo' })");
})->with('section pages');

it('lets the Task tile close the chooser and open the add-task sheet', function (string $uri) {
    $tile = sectionPage($uri)->querySelectorAll('dialog#sheet-add-something button:not([aria-label="Close"])')->item(2);

    expect(trim($tile->querySelector('span')->textContent))->toBe('Task')
        ->and($tile->getAttribute('type'))->toBe('button')
        ->and($tile->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($tile->getAttribute('x-on:click'))->toBe("\$root.close(); \$dispatch('open-sheet', { name: 'add-task' })");
})->with('section pages');

it('lets the Subscription tile close the chooser and open the add-subscription sheet', function (string $uri) {
    $tile = sectionPage($uri)->querySelectorAll('dialog#sheet-add-something button:not([aria-label="Close"])')->item(3);

    expect(trim($tile->querySelector('span')->textContent))->toBe('Subscription')
        ->and($tile->getAttribute('type'))->toBe('button')
        ->and($tile->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($tile->getAttribute('x-on:click'))->toBe("\$root.close(); \$dispatch('open-sheet', { name: 'add-subscription' })");
})->with('section pages');

it('puts the add-subscription sheet on every section page once', function (string $uri) {
    $dialogs = sectionPage($uri)->querySelectorAll('dialog#sheet-add-subscription');

    expect($dialogs)->toHaveCount(1)
        ->and(trim($dialogs->item(0)->querySelector('h2')->textContent))->toBe('New subscription');
})->with('section pages');

it('puts the subscription sheet on every section page once', function (string $uri) {
    $dialogs = sectionPage($uri)->querySelectorAll('dialog#sheet-subscription');

    expect($dialogs)->toHaveCount(1)
        ->and(trim($dialogs->item(0)->querySelector('h2')->textContent))->toBe('Edit subscription');
})->with('section pages');

it('puts the add-task sheet on every section page once', function (string $uri) {
    $dialogs = sectionPage($uri)->querySelectorAll('dialog#sheet-add-task');

    expect($dialogs)->toHaveCount(1)
        ->and(trim($dialogs->item(0)->querySelector('h2')->textContent))->toBe('New task');
})->with('section pages');

it('puts the promo sheet and the add-promo sheet on every section page once', function (string $uri) {
    $page = sectionPage($uri);

    expect($page->querySelectorAll('dialog#sheet-promo'))->toHaveCount(1)
        ->and(trim($page->querySelector('dialog#sheet-promo h2')->textContent))->toBe('The deal')
        ->and($page->querySelectorAll('dialog#sheet-add-promo'))->toHaveCount(1)
        ->and(trim($page->querySelector('dialog#sheet-add-promo h2')->textContent))->toBe('Pin a deal');
})->with('section pages');

it('puts the add-item sheet on every section page once', function (string $uri) {
    $dialogs = sectionPage($uri)->querySelectorAll('dialog#sheet-add-item');

    expect($dialogs)->toHaveCount(1)
        ->and(trim($dialogs->item(0)->querySelector('h2')->textContent))->toBe('Add to your list');
})->with('section pages');

it('shows a flashed toast on the page after a redirect', function () {
    session()->flash('toast', 'Pinned to the board · until Tue');

    $status = sectionPage('/promos')->querySelector('[role="status"]');

    expect(trim($status->textContent))->toBe('')
        ->and($status->getAttribute('x-init'))->toContain('Pinned to the board · until Tue');
});

it('has an empty toast region and a sidebar button that opens the chooser', function (string $uri) {
    $page = sectionPage($uri);
    $status = $page->querySelector('[role="status"]');
    $sidebar = $page->querySelector('nav[aria-label="Main"]');
    $buttons = array_filter(
        iterator_to_array($sidebar->querySelectorAll('button')),
        fn (Element $button) => trim($button->textContent) === 'Add something',
    );
    $button = array_values($buttons)[0];

    expect(trim($status->textContent))->toBe('')
        ->and($buttons)->toHaveCount(1)
        ->and($button->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($button->getAttribute('x-on:click'))->toContain('open-sheet', 'add-something');
})->with('section pages');

it('lets the Home FAB open the chooser', function () {
    $fab = fabButton('/');

    expect($fab->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($fab->getAttribute('x-on:click'))->toContain('open-sheet', 'add-something');
});

it('lets the Groceries FAB open the add-item sheet', function () {
    $fab = fabButton('/groceries');

    expect($fab->getAttribute('aria-label'))->toBe('Add to your list')
        ->and($fab->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($fab->getAttribute('x-on:click'))->toBe("\$dispatch('open-sheet', { name: 'add-item' })");
});

it('lets the Promo board FAB open the add-promo sheet', function () {
    $fab = fabButton('/promos');

    expect($fab->getAttribute('aria-label'))->toBe('Pin a deal')
        ->and($fab->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($fab->getAttribute('x-on:click'))->toBe("\$dispatch('open-sheet', { name: 'add-promo' })");
});

it('lets the To-do FAB open the add-task sheet', function () {
    $fab = fabButton('/todo');

    expect($fab->getAttribute('aria-label'))->toBe('New task')
        ->and($fab->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($fab->getAttribute('x-on:click'))->toBe("\$dispatch('open-sheet', { name: 'add-task' })");
});

it('lets the Subscriptions FAB open the add-subscription sheet', function () {
    $fab = fabButton('/subscriptions');

    expect($fab->getAttribute('aria-label'))->toBe('New subscription')
        ->and($fab->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($fab->getAttribute('x-on:click'))->toBe("\$dispatch('open-sheet', { name: 'add-subscription' })");
});

it('names the sheet each FAB opens', function (Section $section, string $sheet) {
    expect($section->fabSheet())->toBe($sheet);
})->with([
    'home' => [Section::Home, 'add-something'],
    'groceries' => [Section::Groceries, 'add-item'],
    'promo board' => [Section::Promos, 'add-promo'],
    'to-do' => [Section::Todo, 'add-task'],
    'subscriptions' => [Section::Subscriptions, 'add-subscription'],
]);
