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
            ['A', route('promos'), 'Promo photo or screenshot'],
            ['A', route('todo'), 'Task with a due date'],
            ['A', route('subscriptions'), 'Subscription or a free trial'],
        ]);
})->with('section pages');

it('lets the Grocery item tile close the chooser and open the add-item sheet', function (string $uri) {
    $tile = sectionPage($uri)->querySelector('dialog#sheet-add-something button:not([aria-label="Close"])');

    expect($tile->getAttribute('type'))->toBe('button')
        ->and($tile->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($tile->getAttribute('x-on:click'))->toBe("\$root.close(); \$dispatch('open-sheet', { name: 'add-item' })");
})->with('section pages');

it('puts the add-item sheet on every section page once', function (string $uri) {
    $dialogs = sectionPage($uri)->querySelectorAll('dialog#sheet-add-item');

    expect($dialogs)->toHaveCount(1)
        ->and(trim($dialogs->item(0)->querySelector('h2')->textContent))->toBe('Add to your list');
})->with('section pages');

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

it('leaves the other FABs without an action until their sheets exist', function (string $uri) {
    $fab = fabButton($uri);

    expect($fab->hasAttribute('aria-haspopup'))->toBeFalse()
        ->and($fab->hasAttribute('x-on:click'))->toBeFalse();
})->with(['promo board' => '/promos', 'to-do' => '/todo', 'subscriptions' => '/subscriptions']);

it('names the sheet each FAB opens', function (Section $section, ?string $sheet) {
    expect($section->fabSheet())->toBe($sheet);
})->with([
    'home' => [Section::Home, 'add-something'],
    'groceries' => [Section::Groceries, 'add-item'],
    'promo board' => [Section::Promos, null],
    'to-do' => [Section::Todo, null],
    'subscriptions' => [Section::Subscriptions, null],
]);
