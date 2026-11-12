<?php

use App\Livewire\Pages\Groceries;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\PromoBoard;
use App\Livewire\Pages\Subscriptions;
use App\Livewire\Pages\Todo;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->create(['name' => 'Ivayla']));
});

it('renders each section in the app shell', function (
    string $uri,
    string $component,
    string $title,
    string $heading,
    string $ground,
    string $fabLabel,
    string $currentLink,
    string $currentTab,
) {
    $this->travelTo(Carbon::parse('2026-10-29 12:00', 'Europe/Brussels'));

    $response = $this->get($uri)->assertOk()->assertSeeLivewire($component);

    $page = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $navs = $page->querySelectorAll('nav[aria-label="Main"]');
    $sidebar = $navs->item(0);
    $floating = $navs->item(1);
    $current = fn (Element $nav) => array_map(
        fn (Element $link) => preg_replace('/\s+/', ' ', trim($link->textContent)),
        iterator_to_array($nav->querySelectorAll('[aria-current="page"]')),
    );

    expect($navs)->toHaveCount(2)
        ->and($page->title)->toBe($title)
        ->and($page->querySelector('h1')->innerHTML)->toBe($heading)
        ->and($page->querySelector('[data-ground]')->getAttribute('data-ground'))->toBe($ground)
        ->and($floating->querySelector('button')->getAttribute('aria-label'))->toBe($fabLabel)
        ->and($current($sidebar))->toBe([$currentLink])
        ->and($current($floating))->toBe([$currentTab]);
})->with([
    'home' => ['/', Home::class, 'Home · nook', 'Good afternoon,<br><em>Ivayla</em>', 'home', 'Add something', 'Home', 'Home'],
    'groceries' => ['/groceries', Groceries::class, 'Groceries · nook', 'Shopping <em>list</em>', 'groceries', 'Add to your list', 'Groceries 0', 'Groceries'],
    'promo board' => ['/promos', PromoBoard::class, 'Promo board · nook', 'Promo <em>board</em>', 'groceries', 'Pin a deal', 'Promo board 0', 'Groceries'],
    'to-do' => ['/todo', Todo::class, 'To-do · nook', 'Small <em>things</em>', 'todo', 'New task', 'To-do 0', 'To-do'],
    'subscriptions' => ['/subscriptions', Subscriptions::class, 'Subscriptions · nook', 'Subscrip<em>tions</em>', 'subscriptions', 'New subscription', 'Subscriptions 0', 'Subs'],
]);

it('shows today in Brussels in the sidebar and on Home', function () {
    $this->travelTo(Carbon::parse('2026-10-29 20:00', 'Europe/Brussels'));

    $page = HTMLDocument::createFromString($this->get('/')->getContent(), LIBXML_NOERROR);
    $sidebar = $page->querySelector('nav[aria-label="Main"]');
    $eyebrow = $page->querySelector('h1')->previousElementSibling;

    expect($sidebar->lastElementChild->textContent)->toBe('Thursday · 29 October')
        ->and($eyebrow->textContent)->toBe('THURSDAY · 29 OCTOBER');
});

it('navigates with wire:navigate and keeps the toast, the announcer and the sheets in @persist', function (string $uri) {
    $page = HTMLDocument::createFromString($this->get($uri)->assertOk()->getContent(), LIBXML_NOERROR);
    $persisted = fn (Element $element) => $element->closest('[x-persist]') !== null;
    $links = iterator_to_array($page->querySelectorAll('a'));
    $announcer = $page->querySelector('[aria-live="polite"]');
    $dialogs = array_map(
        fn (string $id) => $page->getElementById($id),
        ['sheet-add-something', 'sheet-add-item', 'sheet-promo', 'sheet-add-promo', 'sheet-add-task', 'sheet-task', 'sheet-add-subscription', 'sheet-subscription'],
    );

    expect($links)->not->toBeEmpty()
        ->and(array_map(fn (Element $link) => $link->hasAttribute('wire:navigate'), $links))->each->toBeTrue()
        ->and($page->querySelectorAll('[x-persist]'))->toHaveCount(1)
        ->and($persisted($page->querySelector('[role="status"]')))->toBeTrue()
        ->and($persisted($announcer))->toBeTrue()
        ->and($announcer->textContent)->toBe('')
        ->and(array_map(fn (Element $dialog) => $dialog->tagName, $dialogs))->each->toBe('DIALOG')
        ->and(array_map($persisted, $dialogs))->each->toBeTrue()
        ->and(array_map($persisted, iterator_to_array($page->querySelectorAll('nav[aria-label="Main"], main'))))->toBe([false, false, false]);
})->with(['home' => '/', 'groceries' => '/groceries', 'promo board' => '/promos', 'to-do' => '/todo', 'subscriptions' => '/subscriptions']);

it('colours the navigate progress bar peach', function () {
    expect(config('livewire.navigate'))->toBe(['show_progress_bar' => true, 'progress_bar_color' => '#f0b48a']);
});

it('loads the CSS and JS without preload tags, which a navigation would fetch again', function () {
    $head = HTMLDocument::createFromString($this->withVite()->get('/')->assertOk()->getContent(), LIBXML_NOERROR)->head;

    expect($head->querySelectorAll('link[rel="stylesheet"], script[type="module"]'))->toHaveCount(2)
        ->and($head->querySelectorAll('link[rel="preload"], link[rel="modulepreload"]'))->toHaveCount(0);
});
