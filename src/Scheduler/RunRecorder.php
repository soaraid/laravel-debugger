<?php

namespace Soara\Debugger\Scheduler;

use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Soara\Debugger\Support\Redactor;
use Throwable;

/**
 * Records one row per scheduled task run. Fixes the ways a naive listener
 * gets it wrong:
 *
 * - exit code ≠ 0: Laravel fires "finished" and then "failed"; we write one
 *   failed row, not a success followed by a failure
 * - skipped because the previous run still holds the overlap lock: Laravel
 *   fires "finished" anyway; we write "skipped"
 * - runInBackground: "finished" fires when the process is spawned; we write
 *   "running" and complete the row when schedule:finish reports back
 */
class RunRecorder
{
    /** @var array<int, CarbonImmutable> start time per event object */
    protected array $started = [];

    /** @var array<int, true> events already written for the current run */
    protected array $written = [];

    public function __construct(protected Heartbeat $heartbeat) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(CommandStarting::class, [$this, 'commandStarting']);
        $events->listen(ScheduledTaskStarting::class, [$this, 'starting']);
        $events->listen(ScheduledTaskFinished::class, [$this, 'finished']);
        $events->listen(ScheduledTaskFailed::class, [$this, 'failed']);
        $events->listen(ScheduledTaskSkipped::class, [$this, 'skipped']);
        $events->listen(ScheduledBackgroundTaskFinished::class, [$this, 'backgroundFinished']);
    }

    public function commandStarting(CommandStarting $event): void
    {
        if ($event->command === 'schedule:run') {
            $this->heartbeat->beat(app(Schedule::class));
        }
    }

    public function starting(ScheduledTaskStarting $event): void
    {
        $id = spl_object_id($event->task);
        $this->started[$id] = CarbonImmutable::now();
        unset($this->written[$id]);

        // Cleared so "finished" can tell whether the task actually started:
        // a run skipped by withoutOverlapping() never sets it.
        $event->task->exitCode = null;
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $task = $event->task;
        $ms = (int) round($event->runtime * 1000);

        if ($task->runInBackground) {
            $this->write($task, 'running', null, null, null);

            return;
        }

        if ($task->exitCode === null) {
            $this->write($task, 'skipped', 'previous run still going', null, null);

            return;
        }

        if ((int) $task->exitCode !== 0) {
            $this->write($task, 'failed', 'exit code '.$task->exitCode, (int) $task->exitCode, $ms);

            return;
        }

        $this->write($task, 'ran', null, 0, $ms);
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $id = spl_object_id($event->task);

        // Already written as failed by finished() (non-zero exit code).
        if (isset($this->written[$id])) {
            return;
        }

        $this->write($event->task, 'failed', $event->exception->getMessage(), $event->task->exitCode, $this->elapsed($event->task));
    }

    public function skipped(ScheduledTaskSkipped $event): void
    {
        $task = $event->task;
        $this->started[spl_object_id($task)] = CarbonImmutable::now();

        // withoutOverlapping() works through a skip filter, so an overlap
        // usually lands here rather than in finished().
        $overlapping = false;

        try {
            $overlapping = $task->withoutOverlapping && $task->mutex->exists($task);
        } catch (Throwable) {
            //
        }

        $this->write($task, 'skipped', $overlapping ? 'previous run still going' : 'its conditions were not met', null, null);
    }

    /**
     * Fired by schedule:finish in the background task's own process.
     */
    public function backgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $task = $event->task;
        $code = $task->exitCode === null ? null : (int) $task->exitCode;

        try {
            $row = $this->table()
                ->where('mutex', $task->mutexName())
                ->where('status', 'running')
                ->orderByDesc('id')
                ->first();

            if ($row === null) {
                return;
            }

            $finished = CarbonImmutable::now();

            $this->table()->where('id', $row->id)->update([
                'status' => $code === 0 ? 'ran' : 'failed',
                'reason' => $code === 0 ? null : 'exit code '.($code ?? 'unknown'),
                'exit_code' => $code,
                'finished_at' => $finished,
                'duration_ms' => max(0, (int) CarbonImmutable::parse($row->started_at)->diffInMilliseconds($finished)),
            ]);
        } catch (Throwable $e) {
            $this->reportQuietly($e);
        }
    }

    protected function write(Event $task, string $status, ?string $reason, ?int $exitCode, ?int $durationMs): void
    {
        $id = spl_object_id($task);
        $started = $this->started[$id] ?? CarbonImmutable::now();
        unset($this->started[$id]);
        $this->written[$id] = true;

        try {
            $this->table()->insert([
                'task' => TaskName::for($task),
                'mutex' => $task->mutexName(),
                'expression' => (string) $task->expression,
                'status' => $status,
                'reason' => Redactor::clean($reason),
                'exit_code' => $exitCode,
                'started_at' => $started,
                'finished_at' => $status === 'running' ? null : CarbonImmutable::now(),
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable $e) {
            // Recording must never break the scheduler.
            $this->reportQuietly($e);
        }
    }

    protected function elapsed(Event $task): ?int
    {
        $started = $this->started[spl_object_id($task)] ?? null;

        return $started === null ? null : (int) round($started->diffInMilliseconds(CarbonImmutable::now()));
    }

    protected function table(): Builder
    {
        return $this->connection()->table((string) config('debugger.database.tables.schedule_runs', 'debugger_schedule_runs'));
    }

    protected function connection(): Connection
    {
        return DB::connection(config('debugger.database.connection'));
    }

    protected function reportQuietly(Throwable $e): void
    {
        try {
            report($e);
        } catch (Throwable) {
            //
        }
    }
}
