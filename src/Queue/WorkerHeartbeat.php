<?php

namespace Soara\Debugger\Queue;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * When a queue worker last finished a job. Written at most every 10 seconds
 * per worker process, so busy queues don't pay a cache write per job.
 */
class WorkerHeartbeat
{
    public const KEY = 'debugger:queue:last-processed';

    protected static int $lastWrite = 0;

    public function processed(): void
    {
        if (time() - static::$lastWrite < 10) {
            return;
        }

        static::$lastWrite = time();

        try {
            Cache::forever(self::KEY, CarbonImmutable::now()->toIso8601String());
        } catch (Throwable) {
            //
        }
    }

    public function last(): ?CarbonImmutable
    {
        try {
            $value = Cache::get(self::KEY);
        } catch (Throwable) {
            return null;
        }

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }
}
