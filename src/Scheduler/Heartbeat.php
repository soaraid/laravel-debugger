<?php

namespace Soara\Debugger\Scheduler;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Remembers when schedule:run last started and which tasks it knew about.
 * Web requests never load the schedule, so the Scheduler page reads this
 * snapshot instead.
 */
class Heartbeat
{
    public const KEY = 'debugger:scheduler:heartbeat';

    public function beat(Schedule $schedule): void
    {
        try {
            Cache::forever(self::KEY, [
                'at' => CarbonImmutable::now()->toIso8601String(),
                'tasks' => array_map(fn (Event $event): array => [
                    'task' => TaskName::for($event),
                    'expression' => (string) $event->expression,
                    'timezone' => (string) ($event->timezone instanceof \DateTimeZone ? $event->timezone->getName() : ($event->timezone ?: config('app.timezone'))),
                    'background' => (bool) $event->runInBackground,
                    'without_overlapping' => (bool) $event->withoutOverlapping,
                ], $schedule->events()),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function lastBeat(): ?CarbonImmutable
    {
        $at = $this->snapshot()['at'] ?? null;

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }

    /**
     * @return list<array{task: string, expression: string, timezone: string, background: bool, without_overlapping: bool}>
     */
    public function tasks(): array
    {
        return array_values((array) ($this->snapshot()['tasks'] ?? []));
    }

    public function isLate(): bool
    {
        $last = $this->lastBeat();

        return $last !== null && $last->diffInMinutes(CarbonImmutable::now()) >= (int) config('debugger.scheduler.late_after_minutes', 3);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(): array
    {
        try {
            return (array) Cache::get(self::KEY, []);
        } catch (Throwable) {
            return [];
        }
    }
}
