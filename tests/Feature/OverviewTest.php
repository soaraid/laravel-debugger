<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Soara\Debugger\Debugger;
use Soara\Debugger\Logs\LogReader;
use Soara\Debugger\Scheduler\Heartbeat;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->migrateTestTables();
    Debugger::auth(fn () => true);
    config()->set('cache.default', 'array');
    config()->set('queue.failed.driver', 'database-uuids');
    config()->set('queue.failed.database', config('database.default'));

    // Own storage folder, so log files from the workbench don't count.
    $this->storage = sys_get_temp_dir().'/debugger-overview-'.uniqid();
    app()->useStoragePath($this->storage);
    $this->logFile = storage_path('logs/overview-test.log');
    File::ensureDirectoryExists(dirname($this->logFile));
    $now = now();
    file_put_contents($this->logFile, implode("\n", [
        '['.$now->copy()->subHours(3)->format('Y-m-d H:i:s').'] local.ERROR: Old failure',
        '['.$now->copy()->subMinutes(20)->format('Y-m-d H:i:s').'] local.ERROR: SQLSTATE[HY000] no such table: settings',
        '['.$now->copy()->subMinutes(5)->format('Y-m-d H:i:s').'] local.INFO: All good',
        '['.$now->copy()->subMinutes(4)->format('Y-m-d H:i:s').'] local.CRITICAL: Disk full {"exception":"[object] (RuntimeException(code: 0): Disk full at /app/a.php:1)',
    ])."\n");
    touch($this->logFile, time() + 10);
});

afterEach(fn () => File::deleteDirectory($this->storage));

function overviewRow(array $rows, string $key): ?array
{
    return collect($rows)->firstWhere('key', $key);
}

it('lists sections that need a look first', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendInvoiceMail']),
        'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Connection refused\n#0 trace",
        'failed_at' => now()->subMinutes(18),
    ]);

    $response = $this->getJson('/debugger/api/overview')->assertOk();
    $rows = $response->json('rows');

    expect(array_slice(array_column($rows, 'key'), 0, 2))->toBe(['logs', 'queue'])
        ->and($response->json('attention'))->toBe(2)
        ->and(overviewRow($rows, 'logs'))->toMatchArray(['tone' => 'danger', 'status' => '2 errors', 'query' => ['file' => 'overview-test.log', 'level' => 'error']])
        ->and(overviewRow($rows, 'logs')['text'])->toMatch('/^Last at \d\d:\d\d in overview-test\.log: Disk full$/')
        ->and(overviewRow($rows, 'queue'))->toMatchArray(['tone' => 'danger', 'status' => '1 failed'])
        ->and(overviewRow($rows, 'queue')['text'])->toContain('SendInvoiceMail, TransportException: Connection refused')
        ->and(overviewRow($rows, 'cache'))->toMatchArray(['tone' => 'muted', 'status' => 'array'])
        ->and(overviewRow($rows, 'mail'))->toMatchArray(['tone' => 'muted', 'status' => 'not checked'])
        ->and($response->json('app.Mode'))->toBe('changes allowed')
        ->and($response->json('app.Laravel'))->toBe(app()->version());
});

it('reports a scheduler that stopped and the debug flag in production', function () {
    cache()->forever(Heartbeat::KEY, ['at' => now()->subMinutes(6)->toIso8601String(), 'tasks' => []]);
    app()->detectEnvironment(fn () => 'production');
    config()->set('app.debug', true);

    $rows = $this->getJson('/debugger/api/overview')->assertOk()->json('rows');

    expect($rows[0])->toMatchArray(['key' => 'app', 'tone' => 'danger', 'status' => 'debug on'])
        ->and(overviewRow($rows, 'scheduler'))->toMatchArray(['tone' => 'warning', 'status' => 'not running'])
        ->and(overviewRow($rows, 'scheduler')['text'])->toContain('6 minutes');

    // Back to testing, so tearing down the test database doesn't ask to confirm.
    app()->detectEnvironment(fn () => 'testing');
});

it('counts errors since a moment from the level index', function () {
    $recent = app(LogReader::class)->errorsSince('overview-test.log', now()->subHour());

    expect($recent['count'])->toBe(2)->and($recent['latest']->message)->toStartWith('Disk full');
});

it('is the first section and can be turned off', function () {
    expect(array_key_first($this->shellConfig('/debugger')['modules']))->toBe('overview');

    config()->set('debugger.modules.overview', false);
    $this->getJson('/debugger/api/overview')->assertNotFound();
});

it('counts errors in every log file, not just the newest', function () {
    $browser = storage_path('logs/overview-browser.log');
    file_put_contents($browser, '['.now()->format('Y-m-d H:i:s')."] local.DEBUG: [vite] connected.\n");
    touch($browser, time() + 20);

    try {
        $logs = overviewRow($this->getJson('/debugger/api/overview')->json('rows'), 'logs');
    } finally {
        File::delete($browser);
    }

    expect($logs)->toMatchArray(['tone' => 'danger', 'status' => '2 errors'])
        ->and($logs['query']['file'])->toBe('overview-test.log');
});

it('adds the date to times that are not today', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SyncStock']),
        'exception' => "RuntimeException: Timed out\n#0 trace",
        'failed_at' => now()->subDays(2)->setTime(21, 38),
    ]);

    $queue = overviewRow($this->getJson('/debugger/api/overview')->json('rows'), 'queue');

    expect($queue['text'])->toStartWith('Last at '.now()->subDays(2)->format('M j').', 21:38: SyncStock');
});

it('shows dev as the version outside a tagged release', function () {
    // The package's own checkout: Composer reports 1.0.0+no-version-set.
    expect($this->shellConfig('/debugger')['version'])->toBe('dev');
});
