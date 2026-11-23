<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Soara\Debugger\Audit\AuditLog;
use Soara\Debugger\Connections\Pinger;
use Soara\Debugger\Debugger;
use Soara\Debugger\Http\Controllers\Controller;
use Soara\Debugger\Logs\LogReader;
use Soara\Debugger\Queue\FailedJobs;
use Soara\Debugger\Queue\WorkerHeartbeat;
use Soara\Debugger\Scheduler\Heartbeat;
use Soara\Debugger\Scheduler\SchedulerRuns;
use Soara\Debugger\Storage\StorageTester;
use Throwable;

/**
 * One row per enabled section, saying whether it needs a look. It only
 * reads what is already known (indexes, the cache, the audit log): it never
 * pings, uploads or sends anything, so it stays fast.
 */
class OverviewController extends Controller
{
    private const ORDER = ['danger' => 0, 'warning' => 1, 'ok' => 2, 'muted' => 3];

    public function index(AuditLog $audit): JsonResponse
    {
        $rows = [];

        if (config('app.debug') && app()->isProduction()) {
            $rows[] = $this->row('app', 'App', 'danger', 'debug on', 'APP_DEBUG is on in production. Error pages show code and settings to visitors.', null);
        }

        foreach (['logs', 'queue', 'scheduler', 'connections', 'mail', 'storage', 'cache'] as $module) {
            if (! Debugger::moduleEnabled($module)) {
                continue;
            }

            try {
                $rows[] = match ($module) {
                    'logs' => $this->logs(),
                    'queue' => $this->queue(),
                    'scheduler' => $this->scheduler(),
                    'connections' => $this->connections(),
                    'mail' => $this->mail($audit),
                    'storage' => $this->storage($audit),
                    'cache' => $this->cache(),
                };
            } catch (Throwable $e) {
                $rows[] = $this->row($module, Debugger::MODULES[$module], 'warning', "couldn't check", Str::limit($e->getMessage(), 140), $module);
            }
        }

        // Problems first; otherwise keep the header order.
        $position = array_flip(array_keys($rows));
        uksort($rows, fn (int $a, int $b): int => [self::ORDER[$rows[$a]['tone']], $position[$a]] <=> [self::ORDER[$rows[$b]['tone']], $position[$b]]);

        return response()->json([
            'rows' => array_values($rows),
            'attention' => count(array_filter($rows, fn (array $row): bool => in_array($row['tone'], ['danger', 'warning'], true))),
            'checked_at' => now()->setTimezone(config('app.timezone'))->format('H:i'),
            'app' => [
                'Environment' => (string) app()->environment(),
                'Debug mode' => config('app.debug') ? 'on' : 'off',
                'URL' => (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.url')),
                'Laravel' => Application::VERSION,
                'PHP' => PHP_VERSION,
                'Timezone' => (string) config('app.timezone'),
                'Queue' => (string) config('queue.default'),
                'Mode' => Debugger::readOnly() ? 'read-only' : 'changes allowed',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function logs(): array
    {
        $reader = app(LogReader::class);
        $files = $reader->files();

        if ($files === []) {
            return $this->row('logs', 'Logs', 'muted', 'no files', 'No log files yet.', 'logs');
        }

        // Every log file, not just the newest: laravel.log and browser.log
        // (or a worker log) are written at different times. A file that
        // hasn't changed in the last hour can't hold a newer error.
        $since = CarbonImmutable::now()->subHour();
        $count = 0;
        $capped = false;
        $latest = null;
        $latestFile = null;

        foreach ($files as $file) {
            if ($file['modified'] < $since->getTimestamp()) {
                continue;
            }

            $recent = $reader->errorsSince($file['name'], $since);

            if ($recent['count'] === 0) {
                continue;
            }

            $count += $recent['count'];
            $capped = $capped || $recent['capped'];

            if ($latest === null || $recent['latest']->time?->gt($latest->time)) {
                [$latest, $latestFile] = [$recent['latest'], $file['name']];
            }
        }

        if ($count === 0) {
            return $this->row('logs', 'Logs', 'ok', 'no errors', 'No errors in the last hour.', 'logs');
        }

        $status = ($capped ? $count.'+' : $count).' '.Str::plural('error', $count);
        $time = $this->clock($latest->time);

        return $this->row('logs', 'Logs', 'danger', $status, "Last at {$time} in {$latestFile}: {$latest->summary()}", 'logs', ['file' => $latestFile, 'level' => 'error'], 'Show errors');
    }

    /**
     * @return array<string, mixed>
     */
    protected function queue(): array
    {
        $failed = app(FailedJobs::class)->overview();

        if ($failed['error'] !== null) {
            return $this->row('queue', 'Queue', 'warning', "couldn't check", Str::limit($failed['error'], 140), 'queue');
        }

        if ($failed['total'] > 0) {
            $job = $failed['latest'];
            $when = $this->clock($job['failed_at']);

            return $this->row('queue', 'Queue', 'danger', "{$failed['total']} failed", "Last at {$when}: {$job['name']}, {$job['error']}", 'queue');
        }

        $worker = app(WorkerHeartbeat::class)->last();
        $text = $worker ? "No failed jobs. The worker finished a job {$worker->diffForHumans()}." : 'No failed jobs.';

        return $this->row('queue', 'Queue', 'ok', '0 failed', $text, 'queue');
    }

    /**
     * @return array<string, mixed>
     */
    protected function scheduler(): array
    {
        $heartbeat = app(Heartbeat::class);
        $last = $heartbeat->lastBeat();

        if ($last === null) {
            return $this->row('scheduler', 'Scheduler', 'muted', 'not running', 'Cron entry not added yet.', 'scheduler');
        }

        if ($heartbeat->isLate()) {
            $minutes = (int) $last->diffInMinutes(now());

            return $this->row('scheduler', 'Scheduler', 'warning', 'not running', "No schedule:run for {$minutes} minutes. Is the cron entry still there?", 'scheduler');
        }

        $failed = app(SchedulerRuns::class)->failedSince(CarbonImmutable::now()->subDay());
        $tasks = count($heartbeat->tasks());

        if ($failed > 0) {
            return $this->row('scheduler', 'Scheduler', 'danger', "{$failed} failed", "{$failed} ".Str::plural('run', $failed).' failed in the last 24 hours.', 'scheduler');
        }

        return $this->row('scheduler', 'Scheduler', 'ok', 'running', "Last schedule:run {$last->diffForHumans()}, {$tasks} ".Str::plural('task', $tasks).'.', 'scheduler');
    }

    /**
     * @return array<string, mixed>
     */
    protected function connections(): array
    {
        $pinger = app(Pinger::class);
        $services = $pinger->services();

        if ($services === []) {
            return $this->row('connections', 'Connections', 'muted', 'none set up', 'None in config/debugger.php.', 'connections');
        }

        $results = array_intersect_key($pinger->results(), $services);

        if ($results === []) {
            return $this->row('connections', 'Connections', 'muted', 'not checked', 'Not pinged yet.', 'connections');
        }

        $failing = array_filter($results, fn (array $r): bool => ($r['tone'] ?? 'danger') !== 'ok');
        $names = implode(', ', array_map(fn (string $key): string => $services[$key]['name'], array_keys($failing)));
        $unchecked = count($services) - count($results);
        $note = $unchecked > 0 ? " {$unchecked} not pinged yet." : '';

        if ($failing !== []) {
            $tone = array_filter($failing, fn (array $r): bool => ($r['tone'] ?? null) === 'danger') !== [] ? 'danger' : 'warning';

            return $this->row('connections', 'Connections', $tone, count($failing).' failing', "{$names} failed when last pinged.{$note}", 'connections');
        }

        $latest = max(array_map(fn (array $r): string => (string) ($r['checked_at'] ?? ''), $results));
        $ok = count($results).' of '.count($services).' ok';

        return $this->row('connections', 'Connections', 'ok', $ok, 'Answered when last pinged '.CarbonImmutable::parse($latest)->diffForHumans().'.'.$note, 'connections');
    }

    /**
     * @return array<string, mixed>
     */
    protected function mail(AuditLog $audit): array
    {
        $last = $audit->latest(['mail.test'], 1)[0] ?? null;

        if ($last === null) {
            return $this->row('mail', 'Mail', 'muted', 'not checked', 'No test email sent yet.', 'mail');
        }

        $via = json_decode((string) $last->meta, true)['via'] ?? null;
        $through = $via === 'queue' ? ', through the queue' : '';

        return $this->row('mail', 'Mail', 'ok', 'sent', "Last test email went out {$this->ago($last)}{$through}.", 'mail');
    }

    /**
     * @return array<string, mixed>
     */
    protected function storage(AuditLog $audit): array
    {
        $last = $audit->latest(['storage.upload'], 1)[0] ?? null;

        if ($last === null) {
            return $this->row('storage', 'Storage', 'muted', 'not checked', 'No test upload yet.', 'storage');
        }

        $disk = app(StorageTester::class)->diskName();

        return $this->row('storage', 'Storage', 'ok', 'ok', "Last test upload to the {$disk} disk worked {$this->ago($last)}.", 'storage');
    }

    /**
     * @return array<string, mixed>
     */
    protected function cache(): array
    {
        // Information, not a health check: there is nothing here to fail.
        $config = app()->configurationIsCached() ? 'Config cached' : 'Config not cached';
        $routes = app()->routesAreCached() ? 'routes cached' : 'routes not cached';

        return $this->row('cache', 'Cache', 'muted', (string) config('cache.default'), "{$config}, {$routes}.", 'cache');
    }

    /**
     * "17:31" for today, "Nov 22, 17:31" otherwise, so yesterday's failure
     * doesn't read like today's.
     */
    protected function clock(?\DateTimeInterface $time): string
    {
        if ($time === null) {
            return '–';
        }

        $time = CarbonImmutable::instance($time)->setTimezone(config('app.timezone'));

        return $time->isToday() ? $time->format('H:i') : $time->format('M j, H:i');
    }

    protected function ago(object $auditRow): string
    {
        return CarbonImmutable::parse($auditRow->created_at)->diffForHumans();
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    protected function row(string $key, string $name, string $tone, string $status, string $text, ?string $to, array $query = [], ?string $link = null): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'tone' => $tone,
            'status' => $status,
            'text' => $text,
            'to' => $to,
            'query' => $query,
            'link' => $to ? ($link ?? 'Open '.Debugger::MODULES[$to]) : null,
        ];
    }
}
