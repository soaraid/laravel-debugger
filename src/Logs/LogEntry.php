<?php

namespace Soara\Debugger\Logs;

use Carbon\CarbonImmutable;

final class LogEntry
{
    /** Levels grouped by the tone they are shown in. */
    public const TONES = [
        'emergency' => 'error',
        'alert' => 'error',
        'critical' => 'error',
        'error' => 'error',
        'warning' => 'warning',
        'notice' => 'warning',
        'info' => 'info',
        'debug' => 'debug',
    ];

    public function __construct(
        public readonly ?CarbonImmutable $time,
        public readonly string $environment,
        public readonly string $level,
        public readonly string $message,
        public readonly string $body,
    ) {}

    /**
     * The message without the JSON context Laravel appends to it, for the
     * one-line row: 'Boom {"exception":"[object] ...' becomes 'Boom'.
     */
    public function summary(): string
    {
        $position = strpos($this->message, ' {"');

        return $position === false ? $this->message : rtrim(substr($this->message, 0, $position));
    }

    /**
     * Log context is JSON-encoded, so class names arrive as "App\\Jobs";
     * show them with single backslashes.
     */
    public static function readable(string $text): string
    {
        return str_replace(['\\\\', '\\/', '\\"'], ['\\', '/', '"'], $text);
    }

    public function tone(): string
    {
        return self::TONES[$this->level] ?? 'debug';
    }

    /**
     * Full text after the header line: context JSON and stack trace.
     */
    public function hasDetails(): bool
    {
        return trim($this->body) !== '';
    }

    /**
     * Stack trace frames ("#0 /path/file.php(12): Foo->bar()"), split into
     * application frames and vendor frames.
     *
     * @return array{app: list<string>, vendor: list<string>}
     */
    public function frames(): array
    {
        $app = [];
        $vendor = [];

        foreach (preg_split('/\R/', $this->body) ?: [] as $line) {
            $line = trim($line);

            if (! preg_match('/^#\d+ /', $line)) {
                continue;
            }

            if (str_contains($line, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR) || str_contains($line, '/vendor/')) {
                $vendor[] = $line;
            } else {
                $app[] = $line;
            }
        }

        return ['app' => $app, 'vendor' => $vendor];
    }

    /**
     * The body without the stack trace frames and the JSON leftovers Laravel
     * writes around them: what remains is extra context such as
     * "[previous exception] ..." lines.
     */
    public function context(): string
    {
        $lines = array_filter(
            preg_split('/\R/', $this->body) ?: [],
            fn (string $line): bool => ! preg_match('/^\s*#\d+ /', $line)
                && ! in_array(trim($line), ['[stacktrace]', '"}', '"}]', '}'], true),
        );

        return trim(implode("\n", $lines));
    }

    /**
     * The exception Laravel logged, from its
     * {"exception":"[object] (Class(code: 0): message at /file.php:12)" context.
     *
     * @return array{class: string, code: int, message: string, file: string, line: int}|null
     */
    public function exception(): ?array
    {
        $message = self::readable($this->message);

        if (! preg_match('/\{"exception":"\[object\] \((?<class>[^\s(]+)\(code: (?<code>-?\d+)\): (?<message>.*) at (?<file>\S+):(?<line>\d+)\)\s*$/s', $message, $match)) {
            return null;
        }

        return [
            'class' => $match['class'],
            'code' => (int) $match['code'],
            'message' => trim($match['message']),
            'file' => $match['file'],
            'line' => (int) $match['line'],
        ];
    }

    /**
     * Context data that isn't an exception (e.g. {"url": "...", "user_id": 3}),
     * pretty-printed, or null.
     */
    public function data(): ?string
    {
        $position = strpos($this->message, ' {');

        if ($position === false || $this->exception() !== null) {
            return null;
        }

        $decoded = json_decode(substr($this->message, $position + 1), true);

        return is_array($decoded) ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Stack trace frames in order, each split into where and what.
     *
     * @return list<array{number: int, file: ?string, line: ?int, call: string, vendor: bool}>
     */
    public function stack(): array
    {
        $frames = [];

        foreach (preg_split('/\R/', $this->body) ?: [] as $line) {
            if (! preg_match('/^\s*#(?<n>\d+) (?<rest>.*)$/', $line, $m)) {
                continue;
            }

            $rest = self::readable(trim($m['rest']));
            $file = null;
            $number = null;
            $call = $rest;

            if (preg_match('/^(?<file>.+?)\((?<line>\d+)\): (?<call>.*)$/', $rest, $f)) {
                [$file, $number, $call] = [$f['file'], (int) $f['line'], $f['call']];
            } elseif (preg_match('/^\[internal function\]: (?<call>.*)$/', $rest, $f)) {
                [$file, $call] = ['[internal function]', $f['call']];
            }

            $frames[] = [
                'number' => (int) $m['n'],
                'file' => $file,
                'line' => $number,
                'call' => $call,
                'vendor' => $file !== null && (str_contains($file, '/vendor/') || str_contains($file, '\\vendor\\')),
            ];
        }

        return $frames;
    }
}
