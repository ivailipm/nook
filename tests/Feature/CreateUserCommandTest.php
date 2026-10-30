<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the user', function () {
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
