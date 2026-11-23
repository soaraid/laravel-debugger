<?php

use Soara\Debugger\Assets;

it('serves built assets with long cache headers and without a session', function () {
    $file = app(Assets::class)->file(Assets::ENTRY_CSS);

    $response = $this->get('/debugger/assets/'.$file)->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/css')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->headers->getCookies())->toBeEmpty();
});

it('refuses paths outside dist', function () {
    $this->get('/debugger/assets/..%2F..%2Fcomposer.json')->assertNotFound();
    $this->get('/debugger/assets/.vite/manifest.json')->assertNotFound();
});
