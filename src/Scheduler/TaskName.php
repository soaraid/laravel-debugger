<?php

namespace Soara\Debugger\Scheduler;

use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\Event;

class TaskName
{
    /**
     * A stable, readable name: the description when one is set, otherwise
     * "task:schedule-run" rather than "'/usr/bin/php8.3' 'artisan' task:schedule-run".
     */
    public static function for(Event $event): string
    {
        // A name given with ->description() or ->name() wins.
        if (is_string($event->description) && trim($event->description) !== '') {
            return trim($event->description);
        }

        $command = is_string($event->command) ? $event->command : '';

        if (trim($command) === '') {
            return trim((string) $event->getSummaryForDisplay()) ?: 'Closure';
        }

        $command = str_replace(Application::phpBinary(), '', $command);
        $command = (string) preg_replace('/^\s*[\'"]?artisan[\'"]?\s+/i', '', $command);
        $command = (string) preg_replace('/\s+/', ' ', $command);

        return trim($command) ?: 'artisan';
    }
}
