<?php

namespace Workbench\App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Soara\Debugger\Debugger;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config()->set('auth.providers.users.model', User::class);
        config()->set('debugger.users.columns.last_active', 'last_login_at');
        Debugger::userRole(fn ($user) => ucfirst($user->role));

        // Sample services for the Connections page. 10.255.255.1 is not
        // routable, so it shows what a timeout looks like.
        config()->set('debugger.connections', [
            'httpbin' => ['name' => 'HTTPBin', 'url' => 'https://httpbin.org/status/200', 'timeout' => 5],
            'token' => ['name' => 'Payment gateway', 'url' => 'https://httpbin.org/status/401', 'timeout' => 5],
            'stock' => ['name' => 'Stock API', 'url' => 'http://10.255.255.1/health', 'timeout' => 2],
        ]);
    }

    public function boot(): void
    {
        // Developer accounts can't be impersonated.
        Gate::define('beImpersonated', fn ($actor, $target) => $target->role !== 'developer');

        // A small schedule so the Scheduler page has tasks to show.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->exec('true')->everyMinute()->withoutOverlapping()->description('task:schedule-run');
            $schedule->exec('exit 1')->everyFifteenMinutes()->description('orders:expire-unpaid');
            $schedule->exec('true')->dailyAt('02:00')->description('backup:run');
        });
    }
}
