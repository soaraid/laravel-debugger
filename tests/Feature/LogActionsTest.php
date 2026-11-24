<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Soara\Debugger\Debugger;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->migrateTestTables();
    Debugger::auth(fn () => true);

    // Own storage folder, so the workbench's logs are never touched.
    $this->storage = sys_get_temp_dir().'/debugger-log-actions-'.uniqid();
    app()->useStoragePath($this->storage);
    File::ensureDirectoryExists($this->storage.'/logs');
    $this->file = $this->storage.'/logs/app.log';
    file_put_contents($this->file, "[2026-11-24 10:00:00] local.ERROR: Boom\n[2026-11-24 10:00:01] local.INFO: Fine\n");
});

afterEach(fn () => File::deleteDirectory($this->storage));

it('clears a log file and keeps it', function () {
    expect($this->getJson('/debugger/api/logs?file=app.log')->json('total'))->toBe(2);

    $this->postJson('/debugger/api/logs/clear', ['file' => 'app.log'])->assertJsonPath('notice.text', 'Cleared app.log.');

    expect(file_exists($this->file))->toBeTrue()
        ->and(filesize($this->file))->toBe(0)
        ->and($this->getJson('/debugger/api/logs?file=app.log')->json('total'))->toBe(0)
        ->and(DB::table('debugger_audit_logs')->where('action', 'logs.clear')->value('target'))->toBe('app.log');
});

it('deletes a log file', function () {
    $this->deleteJson('/debugger/api/logs', ['file' => 'app.log'])->assertJsonPath('notice.text', 'Deleted app.log.');

    expect(file_exists($this->file))->toBeFalse()
        ->and(DB::table('debugger_audit_logs')->where('action', 'logs.delete')->exists())->toBeTrue();
});

it('only touches files listed in storage/logs', function () {
    file_put_contents($this->storage.'/outside.log', 'keep me');

    $this->deleteJson('/debugger/api/logs', ['file' => '../outside.log'])->assertJsonPath('notice.tone', 'danger');

    expect(file_get_contents($this->storage.'/outside.log'))->toBe('keep me');
});

it('is blocked in read-only mode', function () {
    config()->set('debugger.read_only', true);

    $this->postJson('/debugger/api/logs/clear', ['file' => 'app.log'])->assertForbidden();
    $this->deleteJson('/debugger/api/logs', ['file' => 'app.log'])->assertForbidden();

    expect(filesize($this->file))->toBeGreaterThan(0);
});
