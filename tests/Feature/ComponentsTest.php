<?php

use App\Enums\Section;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * @param  array<string, mixed>  $data
 */
function renderBlade(string $template, array $data = []): HTMLDocument
{
    return HTMLDocument::createFromString((string) test()->blade($template, $data), LIBXML_NOERROR);
}

it('renders a card with a title and an aside', function () {
    $page = renderBlade('<x-card title="Today" class="col-span-2"><x-slot:aside>3 left</x-slot:aside><p>Laundry</p></x-card>');
    $card = $page->querySelector('section');

    expect(trim($card->querySelector('h2')->textContent))->toBe('Today')
        ->and($card->querySelector('h2')->parentElement->textContent)->toContain('3 left')
        ->and($card->querySelector('p')->textContent)->toBe('Laundry')
        ->and($card->classList->contains('col-span-2'))->toBeTrue()
        ->and($card->classList->contains('rounded-card'))->toBeTrue();
});

it('renders the default card as before', function () {
    $card = renderBlade('<x-card title="Today"><p>Body</p></x-card>')->querySelector('section');
    $header = $card->querySelector('h2')->parentElement;

    expect($card->getAttribute('class'))->toBe('rounded-card border border-card-border bg-card-bg p-4')
        ->and($header->getAttribute('class'))->toBe('mb-1.5 flex items-baseline justify-between')
        ->and($card->querySelector('h2')->getAttribute('class'))->toBe('font-display text-[21px] font-semibold');
});

it('renders the group card with tighter padding and a smaller title', function () {
    $card = renderBlade('<x-card title="Aldi" variant="group" wire:key="group-Aldi"><x-slot:aside>1 left</x-slot:aside><p>Oat milk</p></x-card>')->querySelector('section');
    $header = $card->querySelector('h2')->parentElement;

    expect($card->getAttribute('class'))->toBe('rounded-card border border-card-border bg-card-bg px-4 pt-2 pb-1.5')
        ->and($card->getAttribute('wire:key'))->toBe('group-Aldi')
        ->and($header->getAttribute('class'))->toBe('flex items-baseline justify-between py-1.5')
        ->and($card->querySelector('h2')->getAttribute('class'))->toBe('font-display text-[19px] font-semibold')
        ->and(trim($card->querySelector('h2')->textContent))->toBe('Aldi')
        ->and(trim($header->lastElementChild->textContent))->toBe('1 left')
        ->and($card->querySelector('p')->textContent)->toBe('Oat milk');
});

it('renders a card without a header when it has no title', function () {
    $card = renderBlade('<x-card><p>Body</p></x-card>')->querySelector('section');

    expect($card->querySelector('h2'))->toBeNull()
        ->and($card->querySelector('p')->textContent)->toBe('Body');
});

it('renders a chip as a toggle button', function () {
    $off = renderBlade('<x-chip wire:click="pick(\'Lidl\')">Lidl</x-chip>')->querySelector('button');
    $on = renderBlade('<x-chip :pressed="true" size="sm">Aldi</x-chip>')->querySelector('button');

    expect($off->getAttribute('type'))->toBe('button')
        ->and($off->getAttribute('aria-pressed'))->toBe('false')
        ->and($off->getAttribute('wire:click'))->toBe("pick('Lidl')")
        ->and(trim($off->textContent))->toBe('Lidl')
        ->and($on->getAttribute('type'))->toBe('button')
        ->and($on->getAttribute('aria-pressed'))->toBe('true')
        ->and(trim($on->textContent))->toBe('Aldi');
});

it('renders a checkbox with its state and label', function (string $variant, string $shape) {
    $unchecked = renderBlade("<x-checkbox variant=\"{$variant}\" label=\"Tick off Milk\" />")->querySelector('button');
    $checked = renderBlade("<x-checkbox variant=\"{$variant}\" :checked=\"true\" label=\"Untick Milk\" />")->querySelector('button');

    expect($unchecked->getAttribute('type'))->toBe('button')
        ->and($unchecked->getAttribute('aria-label'))->toBe('Tick off Milk')
        ->and($unchecked->getAttribute('aria-pressed'))->toBe('false')
        ->and($unchecked->querySelector('svg'))->toBeNull()
        ->and($unchecked->querySelector('span')->classList->contains($shape))->toBeTrue()
        ->and($checked->getAttribute('type'))->toBe('button')
        ->and($checked->getAttribute('aria-label'))->toBe('Untick Milk')
        ->and($checked->getAttribute('aria-pressed'))->toBe('true')
        ->and($checked->querySelector('svg[aria-hidden="true"]'))->not->toBeNull()
        ->and($checked->querySelector('span')->classList->contains($shape))->toBeTrue();
})->with([
    'grocery' => ['grocery', 'rounded-checkbox'],
    'task' => ['task', 'rounded-full'],
]);

it('renders a grocery checkbox by default', function () {
    $box = renderBlade('<x-checkbox label="Tick off Milk" />')->querySelector('button > span');

    expect($box->classList->contains('rounded-checkbox'))->toBeTrue();
});

it('renders a sheet as a labelled dialog', function () {
    $dialog = renderBlade('<x-sheet name="add-item" title="Add to your list"><p>Form</p></x-sheet>')->querySelector('dialog#sheet-add-item');
    $heading = $dialog->querySelector('#'.$dialog->getAttribute('aria-labelledby'));

    expect($heading->tagName)->toBe('H2')
        ->and(trim($heading->textContent))->toBe('Add to your list')
        ->and($dialog->querySelector('button[type="button"][aria-label="Close"]'))->not->toBeNull()
        ->and($dialog->querySelector('p')->textContent)->toBe('Form');
});

it('closes a sheet on close-sheet with its name', function () {
    $dialog = renderBlade('<x-sheet name="add-item" title="Add to your list"><p>Form</p></x-sheet>')->querySelector('dialog#sheet-add-item');

    expect($dialog->getAttribute('x-on:open-sheet.window'))->toBe("\$event.detail.name === 'add-item' && ! \$el.open && \$el.showModal()")
        ->and($dialog->getAttribute('x-on:close-sheet.window'))->toBe("\$event.detail.name === 'add-item' && \$el.open && \$el.close()");
});

it('renders the groceries switch as two links with the current one marked', function (Section $current, ?int $listCount, array $links) {
    $nav = renderBlade('<x-groceries-switch :current="$current" :list-count="$listCount" />', ['current' => $current, 'listCount' => $listCount])->querySelector('nav');

    expect($nav->getAttribute('aria-label'))->toBe('Groceries')
        ->and(array_map(
            fn (Element $link) => [trim($link->textContent), $link->getAttribute('href'), $link->getAttribute('aria-current'), $link->classList->contains('bg-cream')],
            iterator_to_array($nav->querySelectorAll('a')),
        ))->toBe(array_map(fn (array $link) => [$link[0], route($link[1]), ...array_slice($link, 2)], $links));
})->with([
    'on the list' => [Section::Groceries, null, [
        ['List', 'groceries', 'page', true],
        ['Promo board', 'promos', null, false],
    ]],
    'on the promo board' => [Section::Promos, 6, [
        ['List · 6', 'groceries', null, false],
        ['Promo board', 'promos', 'page', true],
    ]],
]);

it('renders an empty toast live region', function () {
    $status = renderBlade('<x-toast />')->querySelector('[role="status"]');

    expect($status)->not->toBeNull()
        ->and(trim($status->textContent))->toBe('');
});
