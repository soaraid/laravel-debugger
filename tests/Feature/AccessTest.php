<?php

use Illuminate\Support\Facades\Gate;
use Soara\Debugger\Debugger;

it('refuses access outside local when nothing grants it', function () {
    $this->get('/debugger/logs')->assertForbidden();
    $this->getJson('/debugger/api/logs')->assertForbidden();
});

it('lets the local environment in by default', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/debugger/logs')->assertOk();
});

it('uses the Debugger::auth callback', function () {
    Debugger::auth(fn () => true);
    $this->get('/debugger/logs')->assertOk();

    Debugger::auth(fn () => false);
    $this->get('/debugger/logs')->assertForbidden();
});

it('uses the viewDebugger gate when it is defined', function () {
    Gate::define('viewDebugger', fn ($user = null) => true);

    $this->get('/debugger/logs')->assertOk();
});

it('serves the app shell for every page url, with its config', function () {
    Debugger::auth(fn () => true);

    $this->get('/debugger/queue?page=2')->assertOk()->assertSee('id="debugger"', false);
    $config = $this->shellConfig('/debugger/queue?page=2');

    expect($config['base'])->toBe('/debugger')
        ->and($config['api'])->toBe('/debugger/api')
        ->and(array_keys($config['modules']))->toBe(array_keys(Debugger::MODULES))
        ->and($config['readOnly'])->toBeFalse();
});

it('leaves disabled modules out of the app and their api', function () {
    Debugger::auth(fn () => true);
    config()->set('debugger.modules.users', false);

    $this->getJson('/debugger/api/users')->assertNotFound();
    $config = $this->shellConfig('/debugger');

    expect($config['modules'])->not->toHaveKey('users');
});

it('answers unknown api paths with 404', function () {
    Debugger::auth(fn () => true);

    $this->getJson('/debugger/api/nope')->assertNotFound();
});
