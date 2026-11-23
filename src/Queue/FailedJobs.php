<?php

namespace Soara\Debugger\Queue;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Failed jobs through Laravel's own failer (queue.failer), so every failed
 * job driver works: database, database-uuids, file, dynamodb.
 */
class FailedJobs
{
    public function __construct(protected FailedJobProviderInterface $failer) {}

    /**
     * @return array{jobs: list<array<string, mixed>>, total: int, error: ?string}
     */
    public function page(int $page = 1, int $perPage = 25): array
    {
        try {
            $rows = $this->failer->all();
        } catch (Throwable $e) {
            return ['jobs' => [], 'total' => 0, 'error' => $e->getMessage()];
        }

        $all = array_map(fn (object $row): array => $this->present($row), $rows);

        usort($all, fn (array $a, array $b): int => ($b['failed_at']?->getTimestamp() ?? 0) <=> ($a['failed_at']?->getTimestamp() ?? 0));

        return [
            'jobs' => array_slice($all, max(0, $page - 1) * $perPage, $perPage),
            'total' => count($all),
            'error' => null,
        ];
    }

    /**
     * How many failed jobs there are and the newest one, for the Overview.
     * The database drivers count and fetch one row instead of loading all.
     *
     * @return array{total: int, latest: ?array<string, mixed>, error: ?string}
     */
    public function overview(): array
    {
        try {
            if (! $this->failer instanceof CountableFailedJobProvider) {
                $page = $this->page(1, 1);

                return ['total' => $page['total'], 'latest' => $page['jobs'][0] ?? null, 'error' => $page['error']];
            }

            $total = $this->failer->count();
            $latest = null;

            if ($total > 0) {
                $row = str_starts_with((string) config('queue.failed.driver'), 'database')
                    ? DB::connection(config('queue.failed.database'))->table((string) config('queue.failed.table', 'failed_jobs'))->orderByDesc('id')->first()
                    : null;

                $latest = $row ? $this->present($row) : ($this->page(1, 1)['jobs'][0] ?? null);
            }

            return ['total' => $total, 'latest' => $latest, 'error' => null];
        } catch (Throwable $e) {
            return ['total' => 0, 'latest' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $row = $this->failer->find($id);

        return $row ? $this->present((object) $row) : null;
    }

    public function retry(string $id): void
    {
        $this->ensureExists($id);

        if (Artisan::call('queue:retry', ['id' => [$id]]) !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }
    }

    public function retryAll(): int
    {
        $count = count($this->failer->all());

        if ($count > 0 && Artisan::call('queue:retry', ['id' => ['all']]) !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }

        return $count;
    }

    public function forget(string $id): void
    {
        $this->ensureExists($id);
        $this->failer->forget($id);
    }

    /**
     * Jobs waiting on the default connection, when it is the database driver.
     *
     * @return array{connection: string, queue: string, waiting: ?int}
     */
    public function waiting(): array
    {
        $connection = (string) config('queue.default');
        $config = (array) config("queue.connections.{$connection}", []);
        $queue = (string) ($config['queue'] ?? 'default');
        $waiting = null;

        if (($config['driver'] ?? null) === 'database') {
            try {
                $waiting = DB::connection($config['connection'] ?? null)
                    ->table($config['table'] ?? 'jobs')
                    ->count();
            } catch (Throwable) {
                $waiting = null;
            }
        }

        return ['connection' => $connection, 'queue' => $queue, 'waiting' => $waiting];
    }

    protected function ensureExists(string $id): void
    {
        if ($this->failer->find($id) === null) {
            throw new RuntimeException("Failed job [{$id}] no longer exists.");
        }
    }

    /**
     * First line of the exception with the class shortened:
     * "Symfony\...\TransportException: Connection refused" → "TransportException: Connection refused".
     */
    protected function summary(string $exception): string
    {
        $line = Str::of($exception)->before("\n")->trim()->toString();

        return (string) preg_replace_callback('/^([A-Za-z_][\w\\\\]*\\\\)(\w+)(?=:)/', fn (array $m): string => $m[2], $line);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(object $row): array
    {
        $payload = json_decode((string) ($row->payload ?? ''), true);
        $class = is_array($payload) ? (string) ($payload['displayName'] ?? $payload['job'] ?? 'Job') : 'Job';
        $exception = (string) ($row->exception ?? '');

        return [
            'id' => (string) ($row->uuid ?? $row->id),
            'name' => class_basename($class),
            'class' => $class,
            'connection' => (string) ($row->connection ?? ''),
            'queue' => (string) ($row->queue ?? ''),
            'attempts' => is_array($payload) ? ($payload['attempts'] ?? null) : null,
            'error' => $this->summary($exception),
            'exception' => $exception,
            'failed_at' => isset($row->failed_at) ? CarbonImmutable::parse($row->failed_at) : null,
        ];
    }
}
