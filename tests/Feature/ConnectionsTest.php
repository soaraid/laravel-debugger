<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Soara\Debugger\Debugger;

beforeEach(function () {
    Debugger::auth(fn () => true);
    config()->set('cache.default', 'array');
    config()->set('debugger.connections', [
        'payment' => ['name' => 'Payment API', 'url' => 'https://api.example.com/v1/ping', 'timeout' => 5],
        'gudang' => ['name' => 'Stock API', 'url' => 'https://api.gudang.id/health', 'timeout' => 5],
        'wa' => ['name' => 'WhatsApp gateway', 'url' => 'https://wa.example.com/status', 'method' => 'POST', 'headers' => ['Authorization' => 'Bearer secret-token']],
    ]);
});

it('shows the example config when nothing is set up', function () {
    config()->set('debugger.connections', []);

    $this->getJson('/debugger/api/connections')->assertOk()->assertJsonPath('services', []);
});

it('pings every service and explains the failures', function () {
    Http::fake([
        'api.example.com/*' => Http::response('pong', 200),
        'api.gudang.id/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds'),
        'wa.example.com/*' => Http::response(['message' => 'Invalid API token'], 401),
    ]);

    $this->postJson('/debugger/api/connections/ping-all')->assertJsonPath('notice.text', '2 of 3 services had a problem.');

    $services = collect($this->getJson('/debugger/api/connections')->assertDontSee('secret-token')->json('services'))->keyBy('key');

    expect($services['payment']['result']['label'])->toBe('200 OK')
        ->and($services['gudang']['result']['label'])->toBe('Timeout')
        ->and($services['gudang']['result']['hint'])->toContain('No response within 5 s')
        ->and($services['wa']['result']['label'])->toBe('401 Unauthorized')
        ->and($services['wa']['result']['hint'])->toContain('rejected the credentials');

    Http::assertSent(fn ($request) => $request->url() === 'https://wa.example.com/status' && $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('pings a single service', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $this->postJson('/debugger/api/connections/payment/ping')->assertJsonPath('notice.text', 'Payment API: 200 OK.');
    $this->postJson('/debugger/api/connections/unknown/ping')->assertNotFound();
});
