<?php

namespace Soara\Debugger\Scheduler;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class SchedulerRuns
{
    public function __construct(protected Heartbeat $heartbeat) {}

    /**
     * Tasks from the last schedule:run snapshot, with next and last run.
     *
     * @return list<array<string, mixed>>
     */
    public function tasks(): array
    {
        $last = $this->lastRunPerTask();

        return array_map(function (array $task) use ($last): array {
            $run = $last[$task['task']] ?? null;

            return [
                ...$task,
                'every' => Frequency::describe($task['expression']),
                'next' => $this->nextRun($task['expression'], $task['timezone']),
                'last_at' => $run ? CarbonImmutable::parse($run->started_at) : null,
                'last_status' => $run->status ?? null,
            ];
        }, $this->heartbeat->tasks());
    }

    /**
     * @return array{runs: list<object>, has_more: bool, error: ?string}
     */
    public function recent(int $page = 1, int $perPage = 50): array
    {
        try {
            $rows = $this->table()
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->offset(max(0, $page - 1) * $perPage)
                ->limit($perPage + 1)
                ->get()
                ->all();
        } catch (Throwable $e) {
            return ['runs' => [], 'has_more' => false, 'error' => $e->getMessage()];
        }

        return ['runs' => array_slice($rows, 0, $perPage), 'has_more' => count($rows) > $perPage, 'error' => null];
    }

    /**
     * Runs that failed since a moment, for the Overview.
     */
    public function failedSince(CarbonImmutable $since): int
    {
        try {
            return $this->table()->where('status', 'failed')->where('started_at', '>=', $since)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public function prune(int $days): int
    {
        return $this->table()->where('started_at', '<', CarbonImmutable::now()->subDays($days))->delete();
    }

    /**
     * @return array<string, object>
     */
    protected function lastRunPerTask(): array
    {
        try {
            $latest = $this->table()->selectRaw('max(id) as id')->groupBy('task');

            return $this->table()->whereIn('id', $latest)->get()->keyBy('task')->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function nextRun(string $expression, string $timezone): ?CarbonImmutable
    {
        try {
            $next = (new CronExpression($expression))->getNextRunDate('now', 0, false, $timezone);

            return CarbonImmutable::instance($next);
        } catch (Throwable) {
            return null;
        }
    }

    protected function table(): Builder
    {
        return DB::connection(config('debugger.database.connection'))
            ->table((string) config('debugger.database.tables.schedule_runs', 'debugger_schedule_runs'));
    }
}
