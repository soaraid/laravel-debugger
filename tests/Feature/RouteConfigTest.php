<?php

namespace Soara\Debugger\Tests\Feature;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Soara\Debugger\Assets;
use Soara\Debugger\Debugger;
use Soara\Debugger\Tests\TestCase;

class RouteConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Debugger::auth(fn () => true);
    }

    protected function disabled($app): void
    {
        $app['config']->set('debugger.enabled', false);
    }

    protected function requiresLogin($app): void
    {
        $app['config']->set('debugger.middleware', ['web', 'auth']);
    }

    #[Test]
    #[DefineEnvironment('requiresLogin')]
    public function assets_load_without_the_configured_middleware(): void
    {
        // A guest: with 'auth' on the asset route this would redirect to the
        // login page and the browser would get HTML instead of JavaScript.
        $file = app(Assets::class)->file(Assets::ENTRY_JS);

        $this->get('/debugger/assets/'.$file)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
    }

    protected function customPath($app): void
    {
        $app['config']->set('debugger.path', 'tools/debug');
    }

    #[Test]
    #[DefineEnvironment('disabled')]
    public function it_registers_no_routes_when_disabled(): void
    {
        $this->get('/debugger/logs')->assertNotFound();
    }

    #[Test]
    #[DefineEnvironment('customPath')]
    public function it_can_live_under_a_custom_path(): void
    {
        $this->get('/tools/debug/logs')->assertOk();
        $this->get('/debugger/logs')->assertNotFound();
    }
}
