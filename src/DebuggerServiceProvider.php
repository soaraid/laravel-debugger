<?php

namespace Soara\Debugger;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Soara\Debugger\Console\PruneCommand;
use Soara\Debugger\Http\Middleware\Authorize;
use Soara\Debugger\Http\Middleware\InjectImpersonationBar;
use Soara\Debugger\Logs\LogReader;
use Soara\Debugger\Queue\FailedJobs;
use Soara\Debugger\Queue\WorkerHeartbeat;
use Soara\Debugger\Scheduler\RunRecorder;

class DebuggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/debugger.php', 'debugger');

        $this->app->singleton(Assets::class, fn (): Assets => new Assets(__DIR__.'/../dist'));

        $this->app->bind(LogReader::class, fn (): LogReader => new LogReader(
            storage_path('logs'),
            array_values((array) config('debugger.logs.paths', ['*.log'])),
            storage_path('framework/cache/debugger-logs'),
        ));

        $this->app->bind(FailedJobs::class, fn ($app): FailedJobs => new FailedJobs($app['queue.failer']));

        $this->app->singleton(RunRecorder::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'debugger');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'debugger');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'debugger');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerRoutes();
        $this->registerPublishing();
        $this->registerScheduler();

        if (config('debugger.enabled')) {
            // Through the HTTP kernel, so the kernel re-syncing its groups to
            // the router later doesn't drop the middleware again.
            $kernel = $this->app->make(HttpKernel::class);

            method_exists($kernel, 'appendMiddlewareToGroup')
                ? $kernel->appendMiddlewareToGroup('web', InjectImpersonationBar::class)
                : $this->app['router']->pushMiddlewareToGroup('web', InjectImpersonationBar::class);
        }
    }

    protected function registerRoutes(): void
    {
        if (! config('debugger.enabled')) {
            return;
        }

        $group = [
            'domain' => config('debugger.domain'),
            'prefix' => trim((string) config('debugger.path', 'debugger'), '/'),
            'as' => 'debugger.',
        ];

        // Built CSS, JS and fonts: no middleware at all. They hold nothing
        // sensitive, and without a session or auth redirect they can be
        // cached by the browser (and never come back as a login page).
        Route::group($group, function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/assets.php');
        });

        // Switching back from impersonation: only the web group (session,
        // CSRF), never the configured middleware or the debugger gate, so an
        // impersonated user without debugger access can always return.
        Route::group([...$group, 'middleware' => ['web']], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/impersonation.php');
        });

        Route::group([...$group, 'middleware' => [...(array) config('debugger.middleware', ['web']), Authorize::class]], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    protected function registerScheduler(): void
    {
        $this->app['events']->subscribe(RunRecorder::class);
        $this->app['events']->listen(JobProcessed::class, fn () => app(WorkerHeartbeat::class)->processed());

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([PruneCommand::class]);

        if (config('debugger.scheduler.prune', true)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('debugger:prune')->daily()->runInBackground();
            });
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/debugger.php' => config_path('debugger.php'),
        ], 'debugger-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/debugger'),
        ], 'debugger-views');
    }
}
