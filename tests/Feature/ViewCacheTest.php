<?php

use Illuminate\Support\Facades\Artisan;

it('lets the app cache its views', function () {
    expect(Artisan::call('view:cache'))->toBe(0);

    Artisan::call('view:clear');
});

it('lets the app cache its routes', function () {
    expect(Artisan::call('route:cache'))->toBe(0);

    Artisan::call('route:clear');
});

it('lets the app cache its config', function () {
    expect(Artisan::call('config:cache'))->toBe(0);

    Artisan::call('config:clear');
});
