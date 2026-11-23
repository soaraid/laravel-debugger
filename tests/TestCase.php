<?php

namespace Soara\Debugger\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Soara\Debugger\Debugger;
use Soara\Debugger\DebuggerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DebuggerServiceProvider::class];
    }

    /**
     * Laravel's tables plus failed_jobs, for tests that touch the database.
     */
    public function migrateTestTables(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * The JSON config the app shell renders for the Vue app.
     *
     * @return array<string, mixed>
     */
    public function shellConfig(string $url): array
    {
        $html = (string) $this->get($url)->assertOk()->getContent();
        preg_match('#<script type="application/json" id="debugger-config">(.*?)</script>#s', $html, $match);

        return json_decode($match[1] ?? '{}', true);
    }

    protected function tearDown(): void
    {
        Debugger::auth(null);
        Debugger::userRole(null);
        Debugger::userQuery(null);

        parent::tearDown();
    }
}
