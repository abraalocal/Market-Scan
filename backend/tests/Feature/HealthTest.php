<?php

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});

it('creates only ms_ tables', function () {
    $tables = collect(\Illuminate\Support\Facades\Schema::getTables())->pluck('name')
        ->reject(fn ($t) => in_array($t, ['migrations', 'sqlite_sequence']));
    expect($tables->reject(fn ($t) => str_starts_with($t, 'ms_'))->values()->all())->toBe([]);
});
