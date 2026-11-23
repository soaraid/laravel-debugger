<?php

namespace Soara\Debugger\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

class Format
{
    public static function bytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $bytes : rtrim(rtrim(number_format($bytes, 1, '.', ''), '0'), '.')).' '.$units[$i];
    }

    /**
     * "11:24:07" for today, "Nov 22, 11:24" otherwise.
     */
    public static function time(?DateTimeInterface $time): string
    {
        if ($time === null) {
            return '–';
        }

        $time = Carbon::instance($time)->setTimezone(config('app.timezone'));

        return $time->isToday() ? $time->format('H:i:s') : $time->format('M j, H:i');
    }

    public static function ago(?DateTimeInterface $time): string
    {
        return $time === null ? '–' : Carbon::instance($time)->diffForHumans();
    }

    public static function milliseconds(float $ms): string
    {
        return $ms >= 1000 ? rtrim(rtrim(number_format($ms / 1000, 1, '.', ''), '0'), '.').' s' : number_format($ms).' ms';
    }
}
