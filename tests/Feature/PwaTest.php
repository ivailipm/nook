<?php

use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->withoutVite();
});

it('describes the installable app and its share target in the manifest', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toBe([
        'id' => '/',
        'name' => 'nook',
        'short_name' => 'nook',
        'description' => 'A household hub: groceries and deals, to-dos and subscriptions.',
        'lang' => 'en',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#190a0d',
        'theme_color' => '#190a0d',
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
        'share_target' => [
            'action' => '/promos/share',
            'method' => 'POST',
            'enctype' => 'multipart/form-data',
            'params' => ['title' => 'title', 'text' => 'text', 'files' => [['name' => 'photo', 'accept' => ['image/*']]]],
        ],
    ]);
});

it('ships each icon the manifest names, and the touch icon, at its declared size', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
    $icons = [
        ...array_map(fn (array $icon) => [$icon['src'], $icon['sizes']], $manifest['icons']),
        ['/icons/apple-touch-icon.png', '180x180'],
    ];

    foreach ($icons as [$src, $sizes]) {
        [$width, $height, $type] = getimagesize(public_path(ltrim($src, '/')));

        expect("{$width}x{$height}")->toBe($sizes)
            ->and($type)->toBe(IMAGETYPE_PNG);
    }
});

it('links the manifest, the theme colour and the touch icon on every page', function (string $uri) {
    if ($uri !== '/login') {
        $this->actingAs(User::factory()->create());
    }

    $head = HTMLDocument::createFromString($this->get($uri)->assertOk()->getContent(), LIBXML_NOERROR)->head;

    expect($head->querySelector('meta[name="theme-color"]')->getAttribute('content'))->toBe('#190a0d')
        ->and($head->querySelector('link[rel="manifest"]')->getAttribute('href'))->toBe('/manifest.webmanifest')
        ->and($head->querySelector('link[rel="apple-touch-icon"]')->getAttribute('href'))->toBe('/icons/apple-touch-icon.png');
})->with(['login' => '/login', 'home' => '/', 'groceries' => '/groceries', 'promo board' => '/promos', 'to-do' => '/todo', 'subscriptions' => '/subscriptions']);

it('serves the service worker to guests with the offline page and every built file to precache', function () {
    $response = $this->get('/sw.js')->assertOk();
    $body = $response->getContent();
    $built = collect(json_decode(file_get_contents(public_path('build/manifest.json')), true))
        ->flatMap(fn (array $chunk) => [$chunk['file'], ...($chunk['css'] ?? []), ...($chunk['assets'] ?? [])])
        ->unique();

    expect($response->headers->get('Content-Type'))->toStartWith('text/javascript')
        ->and($response->headers->getCacheControlDirective('no-cache'))->toBeTrue()
        ->and($body)->toMatch('/^const VERSION = "[0-9a-f]{12}";\n/')
        ->and($body)->toContain('"/offline"')
        ->and($body)->toContain(file_get_contents(resource_path('js/sw.js')))
        ->and($built)->not->toBeEmpty();

    $built->each(fn (string $file) => expect($body)->toContain("\"/build/{$file}\""));

    $this->assertGuest();
});

it('shows the offline page to guests', function () {
    $response = $this->get('/offline')->assertOk();
    $page = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $link = $page->querySelector('a');

    expect($page->title)->toBe('Offline · nook')
        ->and(trim($page->querySelector('h1')->previousElementSibling->textContent))->toBe('OFFLINE')
        ->and($page->querySelector('h1')->innerHTML)->toBe("Can't reach <em>nook</em>")
        ->and(trim($page->querySelector('h1')->nextElementSibling->textContent))->toBe('check your signal and try again')
        ->and(trim($page->querySelector('section p')->textContent))->toBe('This page needs a connection. The grocery list still opens without one, as you last saw it here.')
        ->and($page->querySelectorAll('a'))->toHaveCount(1)
        ->and(trim($link->textContent))->toBe('Open the grocery list')
        ->and($link->getAttribute('href'))->toBe('/groceries')
        ->and($link->hasAttribute('wire:navigate'))->toBeFalse()
        ->and(trim($page->querySelector('button[type="button"]')->textContent))->toBe('Try again');
});

it('shows no user data on the offline page to a signed-in user', function () {
    $user = User::factory()->create(['name' => 'Ivayla']);

    $this->actingAs($user)->get('/offline')
        ->assertOk()
        ->assertDontSee('Ivayla')
        ->assertDontSee($user->email);
});

it('renders the hidden read-only banner on the grocery list', function () {
    $this->travelTo(Carbon::parse('2026-11-12 10:42', 'Europe/Brussels'));
    $this->actingAs(User::factory()->create());

    $page = HTMLDocument::createFromString($this->get('/groceries')->assertOk()->getContent(), LIBXML_NOERROR);
    $banner = $page->getElementById('offline-copy');
    $classes = explode(' ', $banner->getAttribute('class'));

    expect($classes)->toContain('hidden', 'in-data-offline-copy:flex')
        ->and($banner->previousElementSibling->querySelector('h1'))->not->toBeNull()
        ->and($banner->nextElementSibling->getAttribute('aria-label'))->toBe('Groceries')
        ->and($banner->querySelector('svg')->getAttribute('aria-hidden'))->toBe('true')
        ->and(trim($banner->querySelector('p')->textContent))->toBe("Can't reach nook, so this is your list as of Thu 12 Nov, 10:42. Ticking and adding need a connection.")
        ->and($banner->querySelectorAll('a'))->toHaveCount(0)
        ->and($banner->querySelector('button')->getAttribute('type'))->toBe('button')
        ->and(trim($banner->querySelector('button')->textContent))->toBe('Try again');
});

it('has no server route for the share target, which the service worker answers', function () {
    $this->actingAs(User::factory()->create())
        ->post('/promos/share')
        ->assertNotFound();
});
