<?php

namespace Soara\Debugger\Support;

use Illuminate\Support\Str;

/**
 * Hides secrets in free text (exception messages, command output) before it
 * is stored or shown.
 */
class Redactor
{
    public static function clean(?string $value, int $limit = 2000): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $patterns = [
            '/((?:password|passwd|pwd|secret|token|api[_-]?key|authorization)["\']?\s*[=:]\s*["\']?)[^\s,;"\']+/i',
            '/((?:mysql|pgsql|postgres|postgresql|redis|rediss|mongodb|smtp|smtps|ftp|sftp|s3|amqp):\/\/)[^\s"\']+/i',
            '/(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/i',
        ];

        return Str::limit((string) preg_replace($patterns, '$1***', $value), $limit);
    }
}
