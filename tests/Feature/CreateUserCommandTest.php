<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

it('creates the user', function () {
    Http::fake();

    $this->artisan('nook:user')
        ->expectsQuestion('Name', 'Test')
        ->expectsQuestion('Email', ' Someone@Example.com ')
        ->expectsQuestion('Password', 'secret123')
        ->expectsQuestion('Confirm password', 'secret123')
        ->expectsOutputToContain('someone@example.com')
        ->assertSuccessful();

    $user = User::sole();

    expect($user->name)->toBe('Test')
        ->and($user->email)->toBe('someone@example.com')
        ->and(Hash::check('secret123', $user->password))->toBeTrue();

    $this->post('/login', ['email' => 'someone@example.com', 'password' => 'secret123'])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);

    Http::assertNothingSent();
});

it('refuses invalid input', function (string $email, string $password, string $confirmation, string $message) {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('nook:user')
        ->expectsQuestion('Name', 'Test')
        ->expectsQuestion('Email', $email)
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(User::count())->toBe(1);
})->with([
    'a taken email' => [' Taken@Example.com ', 'secret123', 'secret123', 'The email has already been taken.'],
    'an invalid email' => ['someone', 'secret123', 'secret123', 'The email field must be a valid email address.'],
    'a short password' => ['someone@example.com', 'short', 'short', 'The password field must be at least 8 characters.'],
    'a mismatched confirmation' => ['someone@example.com', 'secret123', 'secret321', 'The password field confirmation does not match.'],
]);

it('creates the user in production with a 12-character password that is not in a breach', function () {
    $this->app['env'] = 'production';

    Http::fake([
        'api.pwnedpasswords.com/range/*' => Http::response("0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n00D4F6E8FA6EECAD2A3AA415EEC418D38EC:2"),
    ]);

    $this->artisan('nook:user')
        ->expectsQuestion('Name', 'Test')
        ->expectsQuestion('Email', 'someone@example.com')
        ->expectsQuestion('Password', 'plum-kettle7')
        ->expectsQuestion('Confirm password', 'plum-kettle7')
        ->expectsOutputToContain('someone@example.com')
        ->assertSuccessful();

    expect(Hash::check('plum-kettle7', User::sole()->password))->toBeTrue();

    $prefix = substr(strtoupper(sha1('plum-kettle7')), 0, 5);

    Http::assertSent(fn (Request $request) => $request->url() === "https://api.pwnedpasswords.com/range/{$prefix}");
});

it('refuses a password under 12 characters in production', function () {
    $this->app['env'] = 'production';

    Http::fake([
        'api.pwnedpasswords.com/range/*' => Http::response(''),
    ]);

    $this->artisan('nook:user')
        ->expectsQuestion('Name', 'Test')
        ->expectsQuestion('Email', 'someone@example.com')
        ->expectsQuestion('Password', 'plum-kettle')
        ->expectsQuestion('Confirm password', 'plum-kettle')
        ->expectsOutputToContain('The password field must be at least 12 characters.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('refuses a breached password in production', function () {
    $this->app['env'] = 'production';

    $suffix = substr(strtoupper(sha1('password1234')), 5);

    Http::fake([
        'api.pwnedpasswords.com/range/*' => Http::response("0018A45C4D1DEF81644B54AB7F969B88D65:1\r\n{$suffix}:3"),
    ]);

    $this->artisan('nook:user')
        ->expectsQuestion('Name', 'Test')
        ->expectsQuestion('Email', 'someone@example.com')
        ->expectsQuestion('Password', 'password1234')
        ->expectsQuestion('Confirm password', 'password1234')
        ->expectsOutputToContain('The given password has appeared in a data leak. Please choose a different password.')
        ->assertFailed();

    expect(User::count())->toBe(0);
});
