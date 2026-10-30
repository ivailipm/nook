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
    $this->actingAs(User::factory()->create());
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
    $response = $this->get($uri)->assertOk()->assertSeeLivewire($component);

    $page = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $navs = $page->querySelectorAll('nav[aria-label="Main"]');
    $sidebar = $navs->item(0);
    $floating = $navs->item(1);
    $current = fn (Element $nav) => array_map(
        fn (Element $link) => trim($link->textContent),
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
    'home' => ['/', Home::class, 'Home · nook', 'Welcome <em>home</em>', 'home', 'Add something', 'Home', 'Home'],
    'groceries' => ['/groceries', Groceries::class, 'Groceries · nook', 'Shopping <em>list</em>', 'groceries', 'Add to your list', 'Groceries', 'Groceries'],
    'promo board' => ['/promos', PromoBoard::class, 'Promo board · nook', 'Promo <em>board</em>', 'groceries', 'Pin a deal', 'Promo board', 'Groceries'],
    'to-do' => ['/todo', Todo::class, 'To-do · nook', 'Small <em>things</em>', 'todo', 'New task', 'To-do', 'To-do'],
    'subscriptions' => ['/subscriptions', Subscriptions::class, 'Subscriptions · nook', 'Subscrip<em>tions</em>', 'subscriptions', 'New subscription', 'Subscriptions', 'Subs'],
]);

it('shows today in Brussels in the sidebar and on Home', function () {
    $this->travelTo(Carbon::parse('2026-10-29 20:00', 'Europe/Brussels'));

    $page = HTMLDocument::createFromString($this->get('/')->getContent(), LIBXML_NOERROR);
    $sidebar = $page->querySelector('nav[aria-label="Main"]');
    $eyebrow = $page->querySelector('h1')->previousElementSibling;

    expect($sidebar->lastElementChild->textContent)->toBe('Thursday · 29 October')
        ->and($eyebrow->textContent)->toBe('THURSDAY · 29 OCTOBER');
});
