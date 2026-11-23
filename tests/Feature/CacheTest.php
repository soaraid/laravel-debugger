<?php

use Illuminate\Support\Facades\Cache;
use Soara\Debugger\Debugger;

beforeEach(function () {
    Debugger::auth(fn () => true);
    config()->set('cache.default', 'array');
});

it('shows the cache actions and current state', function () {
    $response = $this->getJson('/debugger/api/cache')->assertOk();

    expect(collect($response->json('actions'))->pluck('title'))->toContain('Application cache')
        ->and($response->json('status.Config'))->toBe('not cached');
});

it('clears the application cache', function () {
    Cache::put('greeting', 'hello');

    $this->postJson('/debugger/api/cache/cache')->assertJsonPath('notice.text', 'Ran cache:clear.');

    expect(Cache::get('greeting'))->toBeNull();
});

it('rejects unknown actions and read-only mode', function () {
    $this->postJson('/debugger/api/cache/everything-else')->assertNotFound();

    config()->set('debugger.read_only', true);
    $this->postJson('/debugger/api/cache/view')->assertForbidden();
});
