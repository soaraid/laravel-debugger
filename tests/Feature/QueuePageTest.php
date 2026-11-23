<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Soara\Debugger\Debugger;
use Soara\Debugger\Tests\Fixtures\ExampleJob;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->migrateTestTables();
    Debugger::auth(fn () => true);
    config()->set('queue.default', 'database');
    config()->set('queue.failed.driver', 'database-uuids');
    config()->set('queue.failed.database', config('database.default'));
});

function failJob(string $class = 'App\\Jobs\\SendEmailJob', string $queue = 'default'): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => $queue,
        'payload' => json_encode(['uuid' => $uuid, 'displayName' => $class, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => ['commandName' => ExampleJob::class, 'command' => serialize(new ExampleJob)], 'attempts' => 3]),
        'exception' => "RuntimeException: Connection refused\n#0 /app/Jobs/SendEmailJob.php(20)",
        'failed_at' => now(),
    ]);

    return $uuid;
}

it('lists failed jobs with their error', function () {
    failJob();

    $response = $this->getJson('/debugger/api/queue')->assertOk();

    expect($response->json('jobs.0.name'))->toBe('SendEmailJob')
        ->and($response->json('jobs.0.error'))->toBe('RuntimeException: Connection refused')
        ->and($response->json('total'))->toBe(1)
        ->and($response->json('waiting'))->toBe(['connection' => 'database', 'queue' => 'default', 'waiting' => 0]);
});

it('shows an empty state without failed jobs', function () {
    expect($this->getJson('/debugger/api/queue')->json('jobs'))->toBe([]);
});

it('retries a failed job back onto its queue', function () {
    $uuid = failJob();

    $this->postJson("/debugger/api/queue/{$uuid}/retry")->assertOk()->assertJsonPath('notice.tone', 'success');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('debugger_audit_logs')->where('action', 'queue.retry')->count())->toBe(1);
});

it('retries every failed job', function () {
    failJob();
    failJob('App\\Jobs\\SyncStockJob');

    $this->postJson('/debugger/api/queue/retry-all')->assertJsonPath('notice.text', '2 failed jobs sent back to their queues.');

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('deletes a failed job', function () {
    $uuid = failJob();

    $this->deleteJson("/debugger/api/queue/{$uuid}")->assertJsonPath('notice.tone', 'success');

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('reports a job that no longer exists', function () {
    $this->postJson('/debugger/api/queue/00000000-0000-0000-0000-000000000000/retry')
        ->assertJsonPath('notice.tone', 'danger');
});

it('blocks actions in read-only mode', function () {
    $uuid = failJob();
    config()->set('debugger.read_only', true);

    $this->postJson("/debugger/api/queue/{$uuid}/retry")->assertForbidden();
    $this->deleteJson("/debugger/api/queue/{$uuid}")->assertForbidden();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});
