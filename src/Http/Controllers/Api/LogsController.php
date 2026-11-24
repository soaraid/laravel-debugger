<?php

namespace Soara\Debugger\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Soara\Debugger\Audit\AuditLog;
use Soara\Debugger\Http\Controllers\Controller;
use Soara\Debugger\Logs\LogEntry;
use Soara\Debugger\Logs\LogReader;
use Soara\Debugger\Support\Format;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogsController extends Controller
{
    public function index(Request $request, LogReader $reader): JsonResponse
    {
        $files = $reader->files();
        $names = array_column($files, 'name');
        $file = in_array($request->query('file'), $names, true) ? (string) $request->query('file') : ($names[0] ?? null);
        $level = array_key_exists((string) $request->query('level'), LogEntry::TONES) ? (string) $request->query('level') : null;
        $search = trim((string) $request->query('q', ''));
        $page = max(1, $request->integer('page', 1));
        $perPage = max(1, (int) config('debugger.logs.per_page', 25));

        $result = $file !== null
            ? $reader->entries($file, $page, $perPage, $level ? [$level] : [], $search !== '' ? $search : null)
            : ['entries' => [], 'total' => 0, 'has_more' => false, 'levels' => []];

        return response()->json([
            'files' => array_map(fn (array $f): array => [
                'name' => $f['name'],
                'size' => Format::bytes($f['size']),
                'modified' => $f['modified'],
                'download' => route('debugger.logs.download', ['file' => $f['name']]),
            ], $files),
            'file' => $file,
            'level' => $level,
            'levels' => array_keys(LogEntry::TONES),
            'search' => $search,
            'entries' => array_map(fn (LogEntry $entry): array => $this->present($entry), $result['entries']),
            'total' => $result['total'] ?? 0,
            'level_counts' => $result['levels'] ?? [],
            'pagination' => [
                ...$this->pages($page, count($result['entries']), $perPage, $result['has_more'], $result['total'] ?? null),
                'last_page' => max(1, (int) ceil(($result['total'] ?? 0) / $perPage)),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(LogEntry $entry): array
    {
        return [
            'time' => Format::time($entry->time),
            'datetime' => $entry->time?->toIso8601String(),
            'full_time' => $entry->time?->format('M j, Y H:i:s'),
            'environment' => $entry->environment,
            'level' => $entry->level,
            'tone' => $entry->tone(),
            'summary' => $entry->summary(),
            'message' => LogEntry::readable($entry->message),
            'exception' => $entry->exception(),
            'data' => $entry->data(),
            'context' => LogEntry::readable($entry->context()),
            'stack' => $entry->stack(),
        ];
    }

    public function info(Request $request, LogReader $reader): JsonResponse
    {
        try {
            $info = $reader->info((string) $request->query('file'));
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $location = str_starts_with($info['path'], $base) ? substr($info['path'], strlen($base)) : $info['path'];
        $time = fn (?\DateTimeInterface $t): ?string => $t?->format('M j, Y H:i:s');

        return response()->json([
            'name' => (string) $request->query('file'),
            'location' => $location,
            'size' => Format::bytes($info['size']).($info['size'] >= 1024 ? ' ('.number_format($info['size']).' bytes)' : ''),
            'entries' => number_format($info['entries']),
            'first' => $time($info['first']),
            'modified' => $time(CarbonImmutable::createFromTimestamp($info['modified'], config('app.timezone'))),
        ]);
    }

    /**
     * The raw file. Reading only, so it stays available in read-only mode,
     * and it is written to the audit log like the actions.
     */
    public function download(Request $request, LogReader $reader, AuditLog $audit): BinaryFileResponse
    {
        $file = (string) $request->query('file');
        $path = $reader->path($file) ?? abort(404);

        $audit->record('logs.download', $file);

        return response()->download($path, basename($file), ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function clear(Request $request, LogReader $reader, AuditLog $audit): JsonResponse
    {
        return $this->change($request, $audit, 'logs.clear', function (string $file) use ($reader): string {
            $reader->clear($file);

            return "Cleared {$file}.";
        });
    }

    public function destroy(Request $request, LogReader $reader, AuditLog $audit): JsonResponse
    {
        return $this->change($request, $audit, 'logs.delete', function (string $file) use ($reader): string {
            $reader->delete($file);

            return "Deleted {$file}.";
        });
    }

    /**
     * @param  \Closure(string): string  $action
     */
    protected function change(Request $request, AuditLog $audit, string $name, \Closure $action): JsonResponse
    {
        $file = (string) $request->input('file');

        try {
            $text = $action($file);
            $audit->record($name, $file);

            return $this->notice($text);
        } catch (\Throwable $e) {
            return $this->notice($e->getMessage(), 'danger');
        }
    }
}
