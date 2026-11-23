<?php

use Illuminate\Support\Facades\File;
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

describe('published assets', function () {
    beforeEach(function () {
        $this->public = sys_get_temp_dir().'/debugger-public-'.uniqid();
        mkdir($this->public);
        app()->usePublicPath($this->public);
        app()->forgetInstance(Assets::class);
    });

    afterEach(fn () => File::deleteDirectory($this->public));

    it('links the asset route while nothing is published', function () {
        $assets = app(Assets::class);

        expect($assets->url(Assets::ENTRY_CSS))->toContain('/debugger/assets/assets/')
            ->and($assets->fonts()[0])->toContain('/debugger/assets/assets/');
    });

    it('publishes the files and links the static copies', function () {
        $this->artisan('debugger:publish')->assertSuccessful();

        $assets = app(Assets::class);
        $css = $assets->file(Assets::ENTRY_CSS);

        expect(is_file($this->public.'/vendor/debugger/'.$css))->toBeTrue()
            ->and($assets->url(Assets::ENTRY_CSS))->toBe(asset('vendor/debugger/'.$css))
            ->and($assets->url(Assets::ENTRY_JS))->toContain('/vendor/debugger/assets/')
            ->and($assets->fonts()[0])->toContain('/vendor/debugger/assets/');

        $html = $this->withoutMiddleware()->get('/debugger')->getContent();
        expect($html)->toContain('/vendor/debugger/'.$css);
    });

    it('falls back to the route when the published copy is from another version', function () {
        $this->artisan('debugger:publish')->assertSuccessful();
        $css = app(Assets::class)->file(Assets::ENTRY_CSS);
        unlink($this->public.'/vendor/debugger/'.$css);

        expect(app(Assets::class)->url(Assets::ENTRY_CSS))->toContain('/debugger/assets/'.$css);
    });

    it('removes files left from an older publish', function () {
        mkdir($this->public.'/vendor/debugger/assets', 0777, true);
        file_put_contents($this->public.'/vendor/debugger/assets/app-old.css', 'old');

        $this->artisan('debugger:publish')->assertSuccessful();

        expect(is_file($this->public.'/vendor/debugger/assets/app-old.css'))->toBeFalse();
    });
});
