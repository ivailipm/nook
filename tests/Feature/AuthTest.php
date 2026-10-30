<?php

use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->withoutVite();
});

dataset('sections', ['home' => '/', 'groceries' => '/groceries', 'promo board' => '/promos', 'to-do' => '/todo', 'subscriptions' => '/subscriptions']);

it('sends guests to the login page', function (string $uri) {
    $this->get($uri)->assertRedirect('/login');
})->with('sections');

it('keeps the health check public', function () {
    $this->get('/up')->assertOk();

    $this->assertGuest();
});

it('shows the login page to guests', function () {
    $response = $this->get('/login')->assertOk();

    $page = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $form = $page->querySelector('form');
    $label = fn (string $for) => trim($page->querySelector("label[for=\"{$for}\"]")->textContent);

    expect($page->title)->toBe('Log in · nook')
        ->and($page->querySelector('h1')->innerHTML)->toBe('Welcome <em>back</em>')
        ->and($page->querySelector('[data-ground]')->getAttribute('data-ground'))->toBe('home')
        ->and($page->querySelector('nav[aria-label="Main"]'))->toBeNull()
        ->and($form->getAttribute('method'))->toBe('POST')
        ->and($form->getAttribute('action'))->toBe(url('/login'))
        ->and($label('email'))->toBe('Email')
        ->and($form->querySelector('#email')->getAttribute('type'))->toBe('email')
        ->and($form->querySelector('#email')->getAttribute('autocomplete'))->toBe('username')
        ->and($label('password'))->toBe('Password')
        ->and($form->querySelector('#password')->getAttribute('type'))->toBe('password')
        ->and($form->querySelector('#password')->getAttribute('autocomplete'))->toBe('current-password')
        ->and($form->querySelector('input[name="remember"]')->getAttribute('value'))->toBe('1')
        ->and(trim($form->querySelector('button[type="submit"]')->textContent))->toBe('Log in');
});

it('logs the user in and remembers them', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
        ->assertRedirect('/')
        ->assertCookie(Auth::guard()->getRecallerName());

    $this->assertAuthenticatedAs($user);
});

it('returns to the page the guest asked for after logging in', function () {
    $user = User::factory()->create();

    $this->get('/todo')->assertRedirect('/login');

    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
        ->assertRedirect('/todo');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->assertGuest();
});

it('shows the error under Email and keeps the email', function () {
    $user = User::factory()->create();

    $response = $this->followingRedirects()
        ->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertOk();

    $page = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $email = $page->querySelector('#email');
    $password = $page->querySelector('#password');

    expect($email->getAttribute('value'))->toBe($user->email)
        ->and($email->getAttribute('aria-invalid'))->toBe('true')
        ->and($email->getAttribute('aria-describedby'))->toBe('email-error')
        ->and($page->querySelector('#email-error')->textContent)->toBe('These credentials do not match our records.')
        ->and($password->hasAttribute('value'))->toBeFalse()
        ->and($password->hasAttribute('aria-invalid'))->toBeFalse();
});

it('locks the form after five failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $response = $this->followingRedirects()
        ->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertOk();

    $page = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);

    expect($page->querySelector('#email-error')->textContent)
        ->toMatch('/^Too many login attempts\. Please try again in \d+ seconds\.$/');

    $this->assertGuest();
});

it('sends a signed-in user from the login page to Home', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect('/');
});

it('logs the user out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
    $this->get('/')->assertRedirect('/login');
});

it('has a log out button in the sidebar', function (string $uri) {
    $response = $this->actingAs(User::factory()->create())->get($uri)->assertOk();

    $page = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $sidebar = $page->querySelector('nav[aria-label="Main"]');
    $form = $sidebar->querySelector('form');

    expect($form->getAttribute('method'))->toBe('POST')
        ->and($form->getAttribute('action'))->toBe(url('/logout'))
        ->and($form->querySelector('input[name="_token"]'))->not->toBeNull()
        ->and(trim($form->querySelector('button[type="submit"]')->textContent))->toBe('Log out')
        ->and($form->previousElementSibling->getAttribute('class'))->toBe('flex-1')
        ->and(trim($form->nextElementSibling->textContent))->toBe('Add something');
})->with('sections');

it('has a log out button in the top row on Home', function () {
    $response = $this->actingAs(User::factory()->create())->get('/')->assertOk();

    $page = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $button = $page->querySelector('main button[aria-label="Log out"]');
    $form = $button->closest('form');

    expect($button->getAttribute('type'))->toBe('submit')
        ->and($form->getAttribute('method'))->toBe('POST')
        ->and($form->getAttribute('action'))->toBe(url('/logout'))
        ->and($form->querySelector('input[name="_token"]'))->not->toBeNull();
});

it('has no registration, password reset or two-factor pages', function (string $method, string $uri) {
    $this->call($method, $uri)->assertNotFound();
})->with([
    ['GET', '/register'],
    ['POST', '/register'],
    ['GET', '/forgot-password'],
    ['GET', '/two-factor-challenge'],
]);
