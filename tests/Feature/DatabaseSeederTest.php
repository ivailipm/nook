<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('seeds the local account once', function () {
    $this->seed();
    $this->seed();

    $user = User::sole();

    expect($user->email)->toBe('ivayla@example.com')
        ->and($user->name)->toBe('Ivayla')
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('seeds no user in production', function () {
    $this->app['env'] = 'production';

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0);
});
