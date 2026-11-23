<?php

use Illuminate\Support\Facades\File;
use Soara\Debugger\Logs\LogEntry;
use Soara\Debugger\Logs\LogReader;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/debugger-logs-'.uniqid();
    File::ensureDirectoryExists($this->dir.'/nested');
});

afterEach(fn () => File::deleteDirectory($this->dir));

function writeLog(string $path, array $entries): void
{
    file_put_contents($path, implode("\n", $entries)."\n");
}

it('lists log files inside the root only, newest first', function () {
    writeLog($this->dir.'/laravel.log', ['[2026-11-23 10:00:00] local.INFO: a']);
    writeLog($this->dir.'/nested/worker.log', ['[2026-11-23 10:00:00] local.INFO: b']);
    touch($this->dir.'/laravel.log', time() - 60);
    file_put_contents($this->dir.'/notes.txt', 'not a log');

    $reader = new LogReader($this->dir);

    expect(array_column($reader->files(), 'name'))->toBe(['nested/worker.log', 'laravel.log']);
});

it('refuses names that are not listed files', function () {
    writeLog($this->dir.'/laravel.log', ['[2026-11-23 10:00:00] local.INFO: a']);
    $reader = new LogReader($this->dir);

    expect($reader->path('../../etc/passwd'))->toBeNull()
        ->and($reader->path('/etc/passwd'))->toBeNull()
        ->and($reader->path('laravel.log'))->not->toBeNull();
});

it('parses entries newest first with multi-line bodies', function () {
    writeLog($this->dir.'/laravel.log', [
        '[2026-11-23 10:00:00] production.INFO: First',
        '[2026-11-23 10:00:05] production.ERROR: Boom {"exception":"[object] (RuntimeException(code: 0): Boom at /app/Foo.php:12)',
        '[stacktrace]',
        '#0 /var/www/app/Services/MailService.php(12): send()',
        '#1 /var/www/vendor/laravel/framework/src/Foo.php(10): bar()',
        '#2 {main}',
        '"}',
        '[2026-11-23 10:00:09] production.WARNING: Slow query',
    ]);

    $result = (new LogReader($this->dir))->entries('laravel.log');
    [$warning, $error, $info] = $result['entries'];

    expect($warning->level)->toBe('warning')->and($warning->message)->toBe('Slow query')
        ->and($error->level)->toBe('error')->and($error->tone())->toBe('error')
        ->and($error->environment)->toBe('production')
        ->and($error->frames()['app'])->toHaveCount(2)
        ->and($error->frames()['vendor'])->toHaveCount(1)
        ->and($info->time->format('H:i:s'))->toBe('10:00:00')
        ->and($result['has_more'])->toBeFalse();
});

it('filters by level and search text, and paginates', function () {
    $lines = [];
    for ($i = 1; $i <= 30; $i++) {
        $level = $i % 3 === 0 ? 'ERROR' : 'INFO';
        $lines[] = sprintf('[2026-11-23 10:00:%02d] local.%s: message %d %s', $i, $level, $i, $i === 9 ? 'smtp timeout' : '');
    }
    writeLog($this->dir.'/laravel.log', $lines);
    $reader = new LogReader($this->dir);

    $errors = $reader->entries('laravel.log', 1, 25, ['error']);
    expect($errors['entries'])->toHaveCount(10)->and($errors['has_more'])->toBeFalse();

    $search = $reader->entries('laravel.log', 1, 25, [], 'SMTP');
    expect($search['entries'])->toHaveCount(1)->and($search['entries'][0]->message)->toContain('message 9');

    $page1 = $reader->entries('laravel.log', 1, 25);
    $page2 = $reader->entries('laravel.log', 2, 25);
    expect($page1['has_more'])->toBeTrue()
        ->and($page1['entries'][0]->message)->toContain('message 30')
        ->and($page2['entries'])->toHaveCount(5)
        ->and($page2['has_more'])->toBeFalse();
});

it('reads entries across chunk boundaries in a large file', function () {
    $handle = fopen($this->dir.'/laravel.log', 'w');
    for ($i = 1; $i <= 6000; $i++) {
        fwrite($handle, sprintf("[2026-11-23 10:00:00] local.INFO: entry %d %s\ncontinued line for %d\n", $i, str_repeat('x', 60), $i));
    }
    fclose($handle);

    $reader = new LogReader($this->dir);
    $count = 0;
    $last = null;

    foreach ($reader->readBackwards($this->dir.'/laravel.log') as $entry) {
        $count++;
        expect($entry->body)->toBe('continued line for '.explode(' ', $entry->message)[1]);
        $last = $entry;
    }

    expect($count)->toBe(6000)->and($last->message)->toStartWith('entry 1 ');
});

it('shortens the row summary and unescapes json context', function () {
    $entry = new LogEntry(null, 'local', 'error', 'Boom {"exception":"[object] (App\\\\Jobs\\\\SendEmailJob)', '');

    expect($entry->summary())->toBe('Boom')
        ->and(LogEntry::readable($entry->message))->toBe('Boom {"exception":"[object] (App\\Jobs\\SendEmailJob)');
});

it('counts entries and levels, and reads any page by offset', function () {
    $lines = [];
    for ($i = 1; $i <= 60; $i++) {
        $lines[] = sprintf('[2026-11-23 10:%02d:00] local.%s: entry %d', $i % 60, $i % 4 === 0 ? 'ERROR' : 'INFO', $i);
        $lines[] = "#0 /app/Foo.php({$i}): bar()";
    }
    writeLog($this->dir.'/laravel.log', $lines);
    $reader = new LogReader($this->dir, ['*.log'], $this->dir.'/.index');

    $page3 = $reader->entries('laravel.log', 3, 25);
    expect($page3['total'])->toBe(60)
        ->and($page3['levels'])->toEqual(['info' => 45, 'error' => 15])
        ->and($page3['entries'])->toHaveCount(10)
        ->and($page3['entries'][0]->message)->toBe('entry 10')
        ->and($page3['entries'][0]->body)->toBe('#0 /app/Foo.php(10): bar()');

    $errors = $reader->entries('laravel.log', 1, 25, ['error']);
    expect($errors['total'])->toBe(15)->and($errors['entries'][0]->message)->toBe('entry 60');
});

it('picks up appended entries and starts over after truncation', function () {
    $file = $this->dir.'/laravel.log';
    writeLog($file, ['[2026-11-23 10:00:00] local.INFO: one', '[2026-11-23 10:00:01] local.INFO: two']);
    $reader = new LogReader($this->dir, ['*.log'], $this->dir.'/.index');
    expect($reader->entries('laravel.log')['total'])->toBe(2);

    file_put_contents($file, "[2026-11-23 10:00:02] local.ERROR: three\n#0 trace\n", FILE_APPEND);
    $result = (new LogReader($this->dir, ['*.log'], $this->dir.'/.index'))->entries('laravel.log');
    expect($result['total'])->toBe(3)
        ->and($result['entries'][0]->message)->toBe('three')
        ->and($result['entries'][0]->body)->toBe('#0 trace');

    writeLog($file, ['[2026-11-23 11:00:00] local.INFO: fresh']);
    $fresh = (new LogReader($this->dir, ['*.log'], $this->dir.'/.index'))->entries('laravel.log');
    expect($fresh['total'])->toBe(1)->and($fresh['entries'][0]->message)->toBe('fresh');
});

it('counts search matches for page numbers', function () {
    $lines = [];
    for ($i = 1; $i <= 40; $i++) {
        $lines[] = sprintf('[2026-11-23 10:00:%02d] local.INFO: %s %d', $i, $i % 2 ? 'smtp timeout' : 'ok', $i);
    }
    writeLog($this->dir.'/laravel.log', $lines);
    $reader = new LogReader($this->dir, ['*.log'], $this->dir.'/.index');

    $result = $reader->entries('laravel.log', 1, 15, [], 'SMTP');
    expect($result['total'])->toBe(20)
        ->and($result['entries'])->toHaveCount(15)
        ->and($result['entries'][0]->message)->toBe('smtp timeout 39')
        ->and($result['has_more'])->toBeTrue();
});

it('parses the exception and the stack trace Laravel logs', function () {
    writeLog($this->dir.'/laravel.log', [
        '[2026-11-23 17:31:59] local.ERROR: Argument #1 must be of type Request, null given {"exception":"[object] (TypeError(code: 0): Argument #1 must be of type Request, null given at /app/vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php:1023)',
        '[stacktrace]',
        '#0 /app/vendor/laravel/framework/src/Illuminate/Auth/AuthManager.php(140): Illuminate\\Auth\\SessionGuard->setRequest(NULL)',
        '#1 /app/routes/web.php(12): Illuminate\\Support\\Facades\\Facade::__callStatic(\'guard\', Array)',
        '#2 [internal function]: {closure}()',
        '#3 {main}',
        '"}',
    ]);

    $entry = (new LogReader($this->dir))->entries('laravel.log')['entries'][0];

    expect($entry->exception())->toBe([
        'class' => 'TypeError',
        'code' => 0,
        'message' => 'Argument #1 must be of type Request, null given',
        'file' => '/app/vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php',
        'line' => 1023,
    ])
        ->and($entry->context())->toBe('')
        ->and($entry->stack())->toHaveCount(4)
        ->and($entry->stack()[0])->toMatchArray(['file' => '/app/vendor/laravel/framework/src/Illuminate/Auth/AuthManager.php', 'line' => 140, 'call' => 'Illuminate\\Auth\\SessionGuard->setRequest(NULL)', 'vendor' => true])
        ->and($entry->stack()[1]['vendor'])->toBeFalse()
        ->and($entry->stack()[2]['file'])->toBe('[internal function]')
        ->and($entry->stack()[3]['call'])->toBe('{main}');
});

it('pretty-prints context data that is not an exception', function () {
    writeLog($this->dir.'/laravel.log', ['[2026-11-23 10:00:00] local.DEBUG: [vite] connected. {"url":"http://localhost:8001/","user_id":3}']);

    $entry = (new LogReader($this->dir))->entries('laravel.log')['entries'][0];

    expect($entry->summary())->toBe('[vite] connected.')
        ->and($entry->exception())->toBeNull()
        ->and($entry->data())->toBe("{\n    \"url\": \"http://localhost:8001/\",\n    \"user_id\": 3\n}");
});
