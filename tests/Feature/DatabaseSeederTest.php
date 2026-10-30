<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('seeds the local account once', function (string $environment) {
    $this->app['env'] = $environment;

    $this->seed();
    $this->seed();

    $user = User::sole();

    expect($user->email)->toBe('ivayla@example.com')
        ->and($user->name)->toBe('Ivayla')
        ->and(Hash::check('password', $user->password))->toBeTrue();
})->with(['local', 'testing']);

it('seeds no user outside local and testing', function (string $environment) {
    $this->app['env'] = $environment;

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0);
})->with(['production', 'staging']);
