<?php

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Soara\Debugger\Debugger;
use Soara\Debugger\Scheduler\Heartbeat;

uses(RefreshDatabase::class, WithConsoleEvents::class);

beforeEach(function () {
    $this->migrateTestTables();
    config()->set('cache.default', 'array');
    config()->set('debugger.scheduler.prune', false);
    $this->schedule = app(Schedule::class);
});

function runs(): array
{
    return DB::table('debugger_schedule_runs')->orderBy('id')->get()->all();
}

it('records a successful run once', function () {
    $this->schedule->exec('true')->everyMinute()->description('ok-task');

    Artisan::call('schedule:run');

    expect(runs())->toHaveCount(1)
        ->and(runs()[0]->status)->toBe('ran')
        ->and(runs()[0]->task)->toBe('ok-task')
        ->and(runs()[0]->exit_code)->toBe(0);
});

it('records a non-zero exit code as one failed run', function () {
    $this->schedule->exec('exit 3')->everyMinute()->description('broken');

    Artisan::call('schedule:run');

    expect(runs())->toHaveCount(1)
        ->and(runs()[0]->status)->toBe('failed')
        ->and(runs()[0]->reason)->toBe('exit code 3')
        ->and(runs()[0]->exit_code)->toBe(3);
});

it('records an exception thrown by a callback', function () {
    $this->schedule->call(fn () => throw new RuntimeException('Lock wait timeout exceeded'))->everyMinute()->name('expire-orders');

    Artisan::call('schedule:run');

    expect(runs())->toHaveCount(1)
        ->and(runs()[0]->status)->toBe('failed')
        ->and(runs()[0]->reason)->toBe('Lock wait timeout exceeded');
});

it('records a run skipped by withoutOverlapping as skipped, not as success', function () {
    $event = $this->schedule->exec('true')->everyMinute()->withoutOverlapping()->description('long-task');
    $event->mutex->create($event);

    Artisan::call('schedule:run');

    expect(runs())->toHaveCount(1)
        ->and(runs()[0]->status)->toBe('skipped')
        ->and(runs()[0]->reason)->toBe('previous run still going');
});

it('records a run skipped by its conditions', function () {
    $this->schedule->exec('true')->everyMinute()->when(fn () => false)->description('conditional');

    Artisan::call('schedule:run');

    expect(runs()[0]->status)->toBe('skipped')
        ->and(runs()[0]->reason)->toBe('its conditions were not met');
});

it('completes a background run when schedule:finish reports back', function () {
    $event = $this->schedule->exec('sleep 1')->everyMinute()->runInBackground()->description('bg');

    event(new ScheduledTaskStarting($event));
    event(new ScheduledTaskFinished($event, 0.01));

    expect(runs()[0]->status)->toBe('running')->and(runs()[0]->finished_at)->toBeNull();

    $event->exitCode = 0;
    event(new ScheduledBackgroundTaskFinished($event));

    expect(runs())->toHaveCount(1)
        ->and(runs()[0]->status)->toBe('ran')
        ->and(runs()[0]->finished_at)->not->toBeNull();
});

it('hides secrets in failure reasons', function () {
    $this->schedule->call(fn () => throw new RuntimeException('Could not connect to mysql://root:hunter2@db/app password=hunter2'))->everyMinute()->name('leaky');

    Artisan::call('schedule:run');

    expect(runs()[0]->reason)->not->toContain('hunter2');
});

it('stores a heartbeat and the task list on schedule:run', function () {
    $this->schedule->exec('true')->everyFiveMinutes()->description('five');

    Artisan::call('schedule:run');

    $heartbeat = app(Heartbeat::class);
    expect($heartbeat->lastBeat())->not->toBeNull()
        ->and(collect($heartbeat->tasks())->pluck('task'))->toContain('five');
});

it('shows tasks and runs on the scheduler page', function () {
    Debugger::auth(fn () => true);
    $this->schedule->exec('exit 1')->everyMinute()->description('orders:expire-unpaid');
    Artisan::call('schedule:run');

    $response = $this->getJson('/debugger/api/scheduler')->assertOk();
    $task = collect($response->json('tasks'))->firstWhere('task', 'orders:expire-unpaid');

    expect($task['every'])->toBe('every minute')
        ->and($task['last_status'])->toBe('failed')
        ->and($response->json('runs.0.reason'))->toBe('exit code 1');
});

it('warns when the scheduler stopped running', function () {
    Debugger::auth(fn () => true);
    Artisan::call('schedule:run');
    $this->travel(10)->minutes();

    expect($this->getJson('/debugger/api/scheduler')->json('late_minutes'))->toBe(10);
});

it('prunes old runs', function () {
    DB::table('debugger_schedule_runs')->insert([
        ['task' => 'old', 'expression' => '* * * * *', 'status' => 'ran', 'started_at' => now()->subDays(10)],
        ['task' => 'new', 'expression' => '* * * * *', 'status' => 'ran', 'started_at' => now()],
    ]);

    Artisan::call('debugger:prune');

    expect(collect(runs())->pluck('task')->all())->toBe(['new']);
});

it('pages recent runs 25 at a time with page numbers', function () {
    Debugger::auth(fn () => true);
    $rows = [];
    for ($i = 0; $i < 30; $i++) {
        $rows[] = ['task' => 'ping', 'expression' => '* * * * *', 'status' => 'ran', 'started_at' => now()->subMinutes($i), 'finished_at' => now()->subMinutes($i)];
    }
    DB::table('debugger_schedule_runs')->insert($rows);

    $page = $this->getJson('/debugger/api/scheduler?page=2')->assertOk()->json('pagination');

    expect($page)->toMatchArray(['page' => 2, 'last_page' => 2, 'range' => '26–30 of 30', 'has_previous' => true, 'has_next' => false]);
});
