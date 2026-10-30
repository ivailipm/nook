<?php

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});

it('runs on Brussels time against Postgres', function () {
    expect(config('app.timezone'))->toBe('Europe/Brussels')
        ->and(config('database.default'))->toBe('pgsql');
});
