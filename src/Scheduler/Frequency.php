<?php

namespace Soara\Debugger\Scheduler;

/**
 * Plain-language cron for the common shapes; anything else is shown as is.
 */
class Frequency
{
    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public static function describe(string $expression): ?string
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];

        if (count($parts) !== 5) {
            return null;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;
        $anyDate = $day === '*' && $month === '*';

        if ($parts === ['*', '*', '*', '*', '*']) {
            return 'every minute';
        }

        if ($anyDate && $weekday === '*' && $hour === '*' && preg_match('/^\*\/(\d+)$/', $minute, $m)) {
            return "every {$m[1]} minutes";
        }

        if ($anyDate && $weekday === '*' && $hour === '*' && ctype_digit($minute)) {
            return $minute === '0' ? 'every hour' : sprintf('every hour at :%02d', $minute);
        }

        if ($anyDate && $weekday === '*' && ctype_digit($minute) && preg_match('/^\*\/(\d+)$/', $hour, $m)) {
            return "every {$m[1]} hours";
        }

        if ($anyDate && ctype_digit($minute) && ctype_digit($hour)) {
            $at = sprintf('%02d:%02d', $hour, $minute);

            if ($weekday === '*') {
                return "daily at {$at}";
            }

            if (ctype_digit($weekday) && isset(self::DAYS[(int) $weekday])) {
                return 'every '.self::DAYS[(int) $weekday]." at {$at}";
            }

            if ($weekday === '1-5') {
                return "weekdays at {$at}";
            }
        }

        if ($month === '*' && $weekday === '*' && ctype_digit($day) && ctype_digit($minute) && ctype_digit($hour)) {
            return sprintf('monthly on day %d at %02d:%02d', $day, $hour, $minute);
        }

        return null;
    }
}
