<?php

use Illuminate\Support\Facades\File;
use Soara\Debugger\Debugger;

beforeEach(function () {
    Debugger::auth(fn () => true);
    $this->logDir = storage_path('logs');
    File::ensureDirectoryExists($this->logDir);
    $this->logFile = $this->logDir.'/debugger-test.log';
    file_put_contents($this->logFile, implode("\n", [
        '[2026-11-23 10:00:00] local.INFO: Processing jobs from the [default] queue',
        '[2026-11-23 10:00:05] local.ERROR: Connection could not be established with host "smtp.mailgun.org:587"',
        '[stacktrace]',
        '#0 /var/www/app/Services/MailService.php(12): send()',
        '#1 /var/www/vendor/symfony/mailer/Transport.php(154): start()',
        '[2026-11-23 10:00:09] local.WARNING: Slow query (2.418 ms)',
    ])."\n");
    touch($this->logFile, time() + 10);
});

afterEach(fn () => File::delete($this->logFile));

it('returns the newest log file and its entries', function () {
    $response = $this->getJson('/debugger/api/logs')->assertOk();

    expect($response->json('file'))->toBe('debugger-test.log')
        ->and(collect($response->json('entries'))->pluck('level')->all())->toBe(['warning', 'error', 'info'])
        ->and($response->json('entries.1.stack'))->toHaveCount(2)
        ->and($response->json('entries.1.stack.1.vendor'))->toBeTrue()
        ->and($response->json('total'))->toBe(3)
        ->and($response->json('level_counts'))->toEqual(['info' => 1, 'error' => 1, 'warning' => 1])
        ->and($response->json('pagination.last_page'))->toBe(1);
});

it('filters by level and search', function () {
    $errors = $this->getJson('/debugger/api/logs?file=debugger-test.log&level=error');
    expect($errors->json('entries'))->toHaveCount(1)
        ->and($errors->json('entries.0.summary'))->toContain('smtp.mailgun.org');

    $none = $this->getJson('/debugger/api/logs?file=debugger-test.log&q=nothing-like-this');
    expect($none->json('entries'))->toBe([])->and($none->json('search'))->toBe('nothing-like-this');
});

it('ignores file names that are not listed', function () {
    $response = $this->getJson('/debugger/api/logs?file=../../../../composer.json')->assertOk();

    expect($response->json('file'))->not->toContain('composer.json');
});
