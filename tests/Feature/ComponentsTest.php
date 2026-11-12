<?php

use App\Enums\Section;
use App\Enums\Store;
use App\Enums\SubscriptionCategory;
use App\Models\Promo;
use App\Models\Subscription;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;

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

it('renders the money card with its own border and background, a 20px title and no padding', function () {
    $card = renderBlade('<x-card title="By category" variant="money" class="p-4"><x-slot:aside>per month</x-slot:aside><p>Health</p></x-card>')->querySelector('section');
    $header = $card->querySelector('h2')->parentElement;

    expect($card->getAttribute('class'))->toBe('rounded-card border border-white/12 bg-black/28 p-4')
        ->and($header->getAttribute('class'))->toBe('mb-3 flex items-baseline justify-between')
        ->and($card->querySelector('h2')->getAttribute('class'))->toBe('font-display text-[20px] font-semibold')
        ->and(trim($header->lastElementChild->textContent))->toBe('per month')
        ->and($card->querySelector('p')->textContent)->toBe('Health');
});

it('gives the money card no padding unless it is passed one', function () {
    $card = renderBlade('<x-card variant="money"><p>Body</p></x-card>')->querySelector('section');

    expect($card->getAttribute('class'))->toBe('rounded-card border border-white/12 bg-black/28')
        ->and($card->querySelector('h2'))->toBeNull();
});

it('adds a title class to the card\'s heading only', function () {
    $card = renderBlade('<x-card title="Overdue" variant="group" title-class="text-overdue"><p>Body</p></x-card>')->querySelector('section');

    expect($card->querySelector('h2')->getAttribute('class'))->toBe('font-display text-[19px] font-semibold text-overdue')
        ->and($card->getAttribute('class'))->toBe('rounded-card border border-card-border bg-card-bg px-4 pt-2 pb-1.5')
        ->and($card->hasAttribute('title-class'))->toBeFalse();
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
    $unchecked = renderBlade("<x-checkbox variant=\"{$variant}\" label=\"Tick off Milk\" checked-label=\"Untick Milk\" />")->querySelector('button');
    $checked = renderBlade("<x-checkbox variant=\"{$variant}\" :checked=\"true\" label=\"Tick off Milk\" checked-label=\"Untick Milk\" />")->querySelector('button');

    foreach ([$unchecked, $checked] as $checkbox) {
        $check = $checkbox->querySelector('span > svg[aria-hidden="true"]');

        // The check mark is always there, hidden by class until the button is pressed, so ticking moves nothing.
        expect($checkbox->getAttribute('type'))->toBe('button')
            ->and($checkbox->getAttribute('data-label'))->toBe('Tick off Milk')
            ->and($checkbox->getAttribute('data-checked-label'))->toBe('Untick Milk')
            ->and($checkbox->hasAttribute('checked-label'))->toBeFalse()
            ->and($checkbox->querySelector('span')->classList->contains($shape))->toBeTrue()
            ->and($check->classList->contains('hidden'))->toBeTrue()
            ->and($check->classList->contains('group-aria-pressed/checkbox:block'))->toBeTrue();
    }

    expect($unchecked->getAttribute('aria-label'))->toBe('Tick off Milk')
        ->and($unchecked->getAttribute('aria-pressed'))->toBe('false')
        ->and($checked->getAttribute('aria-label'))->toBe('Untick Milk')
        ->and($checked->getAttribute('aria-pressed'))->toBe('true')
        ->and($checked->innerHTML)->toBe($unchecked->innerHTML);
})->with([
    'grocery' => ['grocery', 'rounded-checkbox'],
    'task' => ['task', 'rounded-full'],
]);

it('uses the label for both states without a checked-label', function () {
    $unchecked = renderBlade('<x-checkbox label="Tick off Milk" />')->querySelector('button');
    $checked = renderBlade('<x-checkbox :checked="true" label="Tick off Milk" />')->querySelector('button');

    foreach ([$unchecked, $checked] as $checkbox) {
        expect($checkbox->getAttribute('aria-label'))->toBe('Tick off Milk')
            ->and($checkbox->getAttribute('data-label'))->toBe('Tick off Milk')
            ->and($checkbox->getAttribute('data-checked-label'))->toBe('Tick off Milk');
    }
});

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

it('renders the groceries switch as two links with the current one marked', function (Section $current, ?int $listCount, ?int $promoCount, array $links) {
    $nav = renderBlade(
        '<x-groceries-switch :current="$current" :list-count="$listCount" :promo-count="$promoCount" />',
        ['current' => $current, 'listCount' => $listCount, 'promoCount' => $promoCount],
    )->querySelector('nav');

    expect($nav->getAttribute('aria-label'))->toBe('Groceries')
        ->and(array_map(
            fn (Element $link) => [trim($link->textContent), $link->getAttribute('href'), $link->getAttribute('aria-current'), $link->classList->contains('bg-cream')],
            iterator_to_array($nav->querySelectorAll('a')),
        ))->toBe(array_map(fn (array $link) => [$link[0], route($link[1]), ...array_slice($link, 2)], $links));
})->with([
    'on the list' => [Section::Groceries, null, null, [
        ['List', 'groceries', 'page', true],
        ['Promo board', 'promos', null, false],
    ]],
    'on the list with a promo count' => [Section::Groceries, null, 6, [
        ['List', 'groceries', 'page', true],
        ['Promo board · 6', 'promos', null, false],
    ]],
    'on the promo board' => [Section::Promos, 6, null, [
        ['List · 6', 'groceries', null, false],
        ['Promo board', 'promos', 'page', true],
    ]],
]);

it('renders the groceries switch without a promo count by default', function () {
    $links = renderBlade('<x-groceries-switch :current="$current" />', ['current' => Section::Groceries])->querySelectorAll('nav a');

    expect(trim($links->item(1)->textContent))->toBe('Promo board');
});

it('makes both groceries switch links navigate with wire:navigate', function () {
    $links = renderBlade('<x-groceries-switch :current="$current" />', ['current' => Section::Promos])->querySelectorAll('nav a');

    expect($links)->toHaveCount(2)
        ->and($links->item(0)->hasAttribute('wire:navigate'))->toBeTrue()
        ->and($links->item(1)->hasAttribute('wire:navigate'))->toBeTrue();
});

/**
 * A Colruyt promo on Thursday 29 October 2026 that ends on the given date.
 */
function componentPromo(string $validUntil, int $tone = 0): Promo
{
    test()->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    return Promo::factory()->create(['store' => Store::Colruyt, 'product' => 'Coffee beans 1 kg', 'deal' => '2+1 free', 'valid_until' => $validUntil, 'tone' => $tone]);
}

/**
 * @return list<Element>
 */
function componentChildren(Element $element): array
{
    return array_values(array_filter(iterator_to_array($element->childNodes), fn ($node) => $node instanceof Element));
}

it('renders a polaroid with its tone, tilt, tape and label', function (bool $linked) {
    $promo = componentPromo('2026-10-31', tone: 4);
    $frame = renderBlade('<x-polaroid :promo="$promo" :linked="$linked" />', ['promo' => $promo, 'linked' => $linked])->querySelector('body > button');
    [$tape, $photo, $labelRow] = componentChildren($frame);
    [$tilt, $tapeTilt] = [['-rotate-[2.2deg]', '-rotate-4'], ['rotate-[1.8deg]', 'rotate-3'], ['-rotate-[1.2deg]', '-rotate-2'], ['rotate-[2.4deg]', 'rotate-5'], ['-rotate-[1.8deg]', '-rotate-3'], ['rotate-[1.2deg]', 'rotate-2']][$promo->id % 6];
    $pill = componentChildren($labelRow)[1] ?? null;

    expect($frame->getAttribute('class'))->toBe("relative flex h-full w-full cursor-pointer flex-col rounded-polaroid bg-paper px-[9px] pt-[9px] pb-1.5 text-left shadow-polaroid {$tilt}")
        ->and($tape->getAttribute('class'))->toBe("absolute -top-[9px] left-1/2 -ml-[29px] h-[18px] w-[58px] opacity-88 bg-tone-4-tape {$tapeTilt}")
        ->and(trim($tape->textContent))->toBe('')
        ->and($photo->getAttribute('class'))->toBe('relative flex aspect-square flex-col gap-[5px] overflow-hidden p-[11px] bg-tone-4-bg text-tone-4-text')
        ->and(array_map(fn (Element $line) => [trim($line->textContent), $line->getAttribute('class')], componentChildren($photo)))->toBe([
            ['Colruyt', 'relative text-[9px] font-bold tracking-[0.12em] uppercase'],
            ['Coffee beans 1 kg', 'relative font-display text-[16px] leading-[1.1] font-semibold'],
            ['2+1 free', 'relative font-display text-[22px] leading-[1.05] font-bold'],
        ])
        ->and($labelRow->getAttribute('class'))->toBe('flex min-h-7 items-center justify-between gap-1.5 pt-1')
        ->and(trim($labelRow->firstElementChild->textContent))->toBe('until Sat')
        ->and($labelRow->firstElementChild->getAttribute('class'))->toBe('font-script text-[18px] font-bold text-[#3a2a20]')
        ->and($frame->querySelectorAll('div, button, a, [tabindex], [role="button"]'))->toHaveCount(0);

    if ($linked) {
        expect(trim($pill->textContent))->toBe('on list')
            ->and($pill->getAttribute('class'))->toBe('inline-flex items-center gap-[3px] rounded-full border border-on-list px-1.5 py-px text-[10px] font-bold text-on-list')
            ->and($pill->querySelector('svg[aria-hidden="true"]'))->not->toBeNull();
    } else {
        expect($pill)->toBeNull();
    }
})->with(['linked' => true, 'not linked' => false]);

it('makes a polaroid a button that opens the promo sheet', function () {
    $promo = componentPromo('2026-10-31');
    $frame = renderBlade('<x-polaroid :promo="$promo" />', ['promo' => $promo])->querySelector('body > *');

    expect($frame->tagName)->toBe('BUTTON')
        ->and($frame->getAttribute('type'))->toBe('button')
        ->and($frame->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($frame->getAttribute('x-on:click'))->toBe("\$dispatch('show-promo', { id: {$promo->id} })");
});

it('names a polaroid by its product, deal, store, until label and list state', function (?string $product, bool $linked, string $label) {
    $promo = componentPromo('2026-10-31');
    $promo->update(['product' => $product]);

    $frame = renderBlade('<x-polaroid :promo="$promo" :linked="$linked" />', ['promo' => $promo, 'linked' => $linked])->querySelector('body > button');

    expect($frame->getAttribute('aria-label'))->toBe($label);
})->with([
    'with a product' => ['Coffee beans 1 kg', false, 'Coffee beans 1 kg, 2+1 free at Colruyt, until Sat'],
    'on the list' => ['Coffee beans 1 kg', true, 'Coffee beans 1 kg, 2+1 free at Colruyt, until Sat, on list'],
    'without a product' => [null, false, '2+1 free at Colruyt, until Sat'],
]);

it('fills the square with the photo and keeps the text on top', function () {
    $promo = componentPromo('2026-10-31');
    $promo->update(['photo_path' => 'promos/photo.jpg']);

    $frame = renderBlade('<x-polaroid :promo="$promo" />', ['promo' => $promo])->querySelector('body > button');
    [$image, $store, $product, $deal] = componentChildren(componentChildren($frame)[1]);

    expect($image->tagName)->toBe('IMG')
        ->and($image->getAttribute('src'))->toBe(route('promos.photo', $promo))
        ->and($image->getAttribute('alt'))->toBe('Photo of the Colruyt deal')
        ->and($image->getAttribute('loading'))->toBe('lazy')
        ->and($image->getAttribute('class'))->toBe('absolute inset-0 size-full object-cover')
        ->and($image->parentElement->classList->contains('relative'))->toBeTrue()
        ->and($store->classList->contains('relative'))->toBeTrue()
        ->and($product->classList->contains('relative'))->toBeTrue()
        ->and($deal->classList->contains('relative'))->toBeTrue();
});

it('has no image on a polaroid without a photo', function () {
    $promo = componentPromo('2026-10-31');

    expect(renderBlade('<x-polaroid :promo="$promo" />', ['promo' => $promo])->querySelector('img'))->toBeNull();
});

it('greys an expired polaroid', function () {
    $promo = componentPromo('2026-10-27');
    $frame = renderBlade('<x-polaroid :promo="$promo" :linked="false" />', ['promo' => $promo])->querySelector('body > button');
    $label = componentChildren($frame)[2]->firstElementChild;

    expect(iterator_to_array($frame->classList))->toContain('opacity-55', 'grayscale')
        ->and(trim($label->textContent))->toBe('expired 27 Oct')
        ->and($label->getAttribute('class'))->toBe('font-script text-[18px] font-bold text-[#6b625a]');
});

it('keeps an active polaroid in colour', function () {
    $promo = componentPromo('2026-10-30');
    $frame = renderBlade('<x-polaroid :promo="$promo" />', ['promo' => $promo])->querySelector('body > button');
    $label = componentChildren($frame)[2]->firstElementChild;

    expect(iterator_to_array($frame->classList))->not->toContain('opacity-55')
        ->and(iterator_to_array($frame->classList))->not->toContain('grayscale')
        ->and(trim($label->textContent))->toBe('ends tomorrow')
        ->and($label->getAttribute('class'))->toBe('font-script text-[18px] font-bold text-ends-soon');
});

it('renders a promo badge, gold, and grey inside a row with data-done', function () {
    $promo = componentPromo('2026-10-31');
    $badge = renderBlade('<x-promo-badge :promo="$promo" />', ['promo' => $promo])->querySelector('body > button');
    $wide = $badge->querySelector('[class~="wide:inline"]');

    expect($badge->getAttribute('class'))->toBe('relative inline-flex max-w-[60%] cursor-pointer items-center gap-[5px] rounded-full px-2.5 py-1 text-[12px] font-bold whitespace-nowrap before:absolute before:inset-x-0 before:-inset-y-2.5 bg-accent text-on-accent in-data-done:bg-white/12 in-data-done:text-cream/80')
        ->and($badge->hasAttribute('done'))->toBeFalse()
        ->and($badge->getAttribute('type'))->toBe('button')
        ->and($badge->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($badge->getAttribute('aria-label'))->toBe('Show the Colruyt deal: 2+1 free')
        ->and($badge->getAttribute('x-on:click'))->toBe("\$dispatch('show-promo', { id: {$promo->id} })")
        ->and($badge->querySelector('svg[aria-hidden="true"]')->getAttribute('class'))->toContain('size-[13px] flex-none stroke-[2.2]')
        ->and(trim($wide->previousSibling->textContent))->toBe('2+1 free')
        ->and($wide->textContent)->toBe(' · Colruyt')
        ->and($wide->getAttribute('class'))->toBe('hidden wide:inline')
        ->and(preg_replace('/\s+/u', ' ', trim($badge->textContent)))->toBe('2+1 free · Colruyt')
        ->and($badge->querySelectorAll('button, a, [tabindex]'))->toHaveCount(0);
});

it('renders an empty toast live region', function () {
    $status = renderBlade('<x-toast />')->querySelector('[role="status"]');

    expect($status)->not->toBeNull()
        ->and(trim($status->textContent))->toBe('')
        ->and($status->hasAttribute('x-init'))->toBeFalse()
        ->and($status->getAttribute('x-on:toast.window'))->toBe('show($event.detail.message)');
});

it('shows a flashed toast once the page has loaded, not in the markup', function () {
    session()->flash('toast', 'Pinned to the board · until Tue');

    $status = renderBlade('<x-toast />')->querySelector('[role="status"]');

    expect(trim($status->textContent))->toBe('')
        ->and($status->getAttribute('x-init'))->toContain('setTimeout', 'Pinned to the board · until Tue');
});

it('renders existing fields as before, and a date field in mono with a dark picker', function () {
    $this->withViewErrors([]);

    $text = renderBlade('<x-field label="Item" name="itemName" placeholder="Milk" />')->querySelector('input');
    $date = renderBlade('<x-field label="From" name="validFrom" type="date" />')->querySelector('input');
    $gold = renderBlade('<x-field label="Until" name="validUntil" type="date" gold />')->querySelector('input');

    expect($text->getAttribute('class'))->toBe('min-h-[46px] w-full rounded-[12px] border bg-black/30 px-3.5 text-[16px] border-input-border')
        ->and($text->hasAttribute('type'))->toBeFalse()
        ->and($date->getAttribute('class'))->toBe('min-h-[46px] w-full rounded-[12px] border bg-black/30 px-3 font-mono text-[15px] [color-scheme:dark] border-input-border')
        ->and($date->getAttribute('type'))->toBe('date')
        ->and($gold->getAttribute('class'))->toBe('min-h-[46px] w-full rounded-[12px] border bg-black/30 px-3 font-mono text-[15px] [color-scheme:dark] border-gold');
});

it('keeps the md checkbox as it was, its fill following aria-pressed', function (string $variant, string $box, string $check) {
    foreach (['false', 'true'] as $checked) {
        $checkbox = renderBlade("<x-checkbox variant=\"{$variant}\" :checked=\"{$checked}\" label=\"Tick off Milk\" />")->querySelector('button');

        expect($checkbox->getAttribute('class'))->toBe('group/checkbox grid size-11 flex-none cursor-pointer place-items-center')
            ->and($checkbox->querySelector('span')->getAttribute('class'))->toBe($box)
            ->and($checkbox->querySelector('svg')->getAttribute('class'))->toContain($check);
    }
})->with([
    'grocery' => ['grocery', 'grid size-[22px] place-items-center border-2 rounded-checkbox border-cream-faint group-aria-pressed/checkbox:border-gold group-aria-pressed/checkbox:bg-gold group-aria-pressed/checkbox:text-on-gold', 'size-[15px] stroke-[3.5] hidden group-aria-pressed/checkbox:block'],
    'task' => ['task', 'grid size-[22px] place-items-center border-2 rounded-full border-cream-faint group-aria-pressed/checkbox:border-apricot group-aria-pressed/checkbox:bg-apricot group-aria-pressed/checkbox:text-on-apricot', 'size-3.5 stroke-[3.5] hidden group-aria-pressed/checkbox:block'],
]);

it('renders the sm checkbox with an 18px box and a 44px hit area, its radius the same for both variants', function (string $variant, string $fill) {
    $open = renderBlade("<x-checkbox variant=\"{$variant}\" size=\"sm\" label=\"Tick off Milk\" />")->querySelector('button');
    $ticked = renderBlade("<x-checkbox variant=\"{$variant}\" size=\"sm\" :checked=\"true\" label=\"Untick Milk\" />")->querySelector('button');

    expect($open->getAttribute('class'))->toBe('group/checkbox grid size-11 flex-none cursor-pointer place-items-center')
        ->and($open->querySelector('span')->getAttribute('class'))->toBe("grid size-[18px] place-items-center rounded-[4px] border-[1.5px] border-cream-faint {$fill}")
        ->and($open->querySelector('svg')->getAttribute('class'))->toContain('size-[13px] stroke-[3.5] hidden group-aria-pressed/checkbox:block')
        ->and($open->hasAttribute('size'))->toBeFalse()
        ->and($ticked->querySelector('span')->getAttribute('class'))->toBe("grid size-[18px] place-items-center rounded-[4px] border-[1.5px] border-cream-faint {$fill}")
        ->and($ticked->querySelector('svg')->getAttribute('class'))->toContain('size-[13px] stroke-[3.5] hidden group-aria-pressed/checkbox:block');
})->with([
    'grocery' => ['grocery', 'group-aria-pressed/checkbox:border-gold group-aria-pressed/checkbox:bg-gold group-aria-pressed/checkbox:text-on-gold'],
    'task' => ['task', 'group-aria-pressed/checkbox:border-apricot group-aria-pressed/checkbox:bg-apricot group-aria-pressed/checkbox:text-on-apricot'],
]);

it('renders the home promo badge in soft peach with the store at every width', function () {
    $promo = componentPromo('2026-10-31');
    $badge = renderBlade('<x-promo-badge :promo="$promo" variant="home" />', ['promo' => $promo])->querySelector('body > button');

    expect($badge->getAttribute('class'))->toBe('relative inline-flex max-w-[60%] cursor-pointer items-center gap-[5px] rounded-full bg-[#f6d9c4] px-[9px] py-[3px] text-[11px] font-bold whitespace-nowrap text-[#4a1d10] before:absolute before:inset-x-0 before:-inset-y-3')
        ->and($badge->hasAttribute('variant'))->toBeFalse()
        ->and($badge->getAttribute('aria-haspopup'))->toBe('dialog')
        ->and($badge->getAttribute('aria-label'))->toBe('Show the Colruyt deal: 2+1 free')
        ->and($badge->getAttribute('x-on:click'))->toBe("\$dispatch('show-promo', { id: {$promo->id} })")
        ->and($badge->querySelector('svg[aria-hidden="true"]')->getAttribute('class'))->toContain('size-3 flex-none stroke-[2.2]')
        ->and($badge->querySelector('[class~="wide:inline"]'))->toBeNull()
        ->and($badge->querySelector('[class~="hidden"]'))->toBeNull()
        ->and(preg_replace('/\s+/u', ' ', trim($badge->textContent)))->toBe('2+1 free · Colruyt');
});

it('renders a compact polaroid with Home\'s values', function () {
    $promo = componentPromo('2026-10-31', tone: 4);
    $frame = renderBlade('<x-polaroid :promo="$promo" :linked="true" compact />', ['promo' => $promo])->querySelector('body > button');
    [$tape, $photo, $labelRow] = componentChildren($frame);
    [$tilt, $tapeTilt] = [['-rotate-[2.2deg]', '-rotate-4'], ['rotate-[1.8deg]', 'rotate-3'], ['-rotate-[1.2deg]', '-rotate-2'], ['rotate-[2.4deg]', 'rotate-5'], ['-rotate-[1.8deg]', '-rotate-3'], ['rotate-[1.2deg]', 'rotate-2']][$promo->id % 6];
    $pill = componentChildren($labelRow)[1];

    expect($frame->getAttribute('class'))->toBe("relative flex h-full w-full cursor-pointer flex-col rounded-polaroid bg-paper px-2 pt-2 pb-1.5 text-left shadow-[0_10px_22px_rgb(0_0_0/0.38)] {$tilt}")
        ->and($frame->hasAttribute('compact'))->toBeFalse()
        ->and($tape->getAttribute('class'))->toBe("absolute -top-[9px] left-1/2 -ml-[27px] h-[17px] w-[54px] opacity-88 bg-tone-4-tape {$tapeTilt}")
        ->and($photo->getAttribute('class'))->toBe('relative flex aspect-square flex-col gap-1 overflow-hidden p-2.5 bg-tone-4-bg text-tone-4-text')
        ->and(array_map(fn (Element $line) => [trim($line->textContent), $line->getAttribute('class')], componentChildren($photo)))->toBe([
            ['Colruyt', 'relative text-[9px] font-bold tracking-[0.12em] uppercase'],
            ['Coffee beans 1 kg', 'relative font-display text-[15px] leading-[1.1] font-semibold'],
            ['2+1 free', 'relative font-display text-[20px] leading-[1.05] font-bold'],
        ])
        ->and($labelRow->getAttribute('class'))->toBe('flex min-h-[26px] items-center justify-between gap-1.5 pt-1')
        ->and($labelRow->firstElementChild->getAttribute('class'))->toBe('font-script text-[17px] font-bold text-[#3a2a20]')
        ->and(trim($pill->textContent))->toBe('on list')
        ->and($pill->querySelector('svg'))->toBeNull();
});

it('renders a link chip as a link with a dot and a 44px hit area, not a toggle', function () {
    $chip = renderBlade('<x-link-chip href="/groceries" dot="bg-peach">6 to buy</x-link-chip>')->querySelector('body > a');
    $dot = $chip->firstElementChild;

    expect($chip->getAttribute('href'))->toBe('/groceries')
        ->and($chip->getAttribute('class'))->toBe('relative inline-flex min-h-[34px] items-center gap-2 rounded-full border border-white/14 bg-black/25 px-3.5 text-[13px] font-semibold text-cream before:absolute before:inset-x-0 before:-inset-y-[6px]')
        ->and($chip->hasAttribute('aria-pressed'))->toBeFalse()
        ->and($chip->hasAttribute('dot'))->toBeFalse()
        ->and(trim($chip->textContent))->toBe('6 to buy')
        ->and($dot->getAttribute('aria-hidden'))->toBe('true')
        ->and($dot->getAttribute('class'))->toBe('size-[7px] rounded-full bg-peach');
});

it('makes a link chip navigate with wire:navigate', function () {
    $chip = renderBlade('<x-link-chip href="/groceries" dot="bg-peach">6 to buy</x-link-chip>')->querySelector('body > a');

    expect($chip->hasAttribute('wire:navigate'))->toBeTrue()
        ->and($chip->getAttribute('wire:navigate'))->toBe('');
});

it('renders a subscription avatar', function (?string $cancelledAt, string $classes) {
    $subscription = Subscription::factory()->create(['name' => 'Éclair', 'category' => SubscriptionCategory::Health, 'cancelled_at' => $cancelledAt]);
    $avatar = renderBlade('<x-subscription-avatar :subscription="$subscription" />', ['subscription' => $subscription])->querySelector('body > span');

    expect($avatar->textContent)->toBe('É')
        ->and($avatar->getAttribute('aria-hidden'))->toBe('true')
        ->and($avatar->getAttribute('class'))->toBe($classes);
})->with([
    'live' => [null, 'grid size-[34px] flex-none place-items-center rounded-avatar bg-white/8 font-display font-bold inset-ring-[1.5px] inset-ring-cat-health'],
    'cancelled' => ['2026-10-28', 'grid size-[34px] flex-none place-items-center rounded-avatar bg-white/8 font-display font-bold inset-ring-[1.5px] inset-ring-cat-health opacity-60'],
]);
