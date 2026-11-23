<?php

namespace Soara\Debugger\Logs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads Laravel log files without loading them into memory. An index of
 * where each entry starts (LogIndex) gives totals and page numbers; a page
 * reads only its own entries. Search reads the file backwards in chunks.
 */
class LogReader
{
    private const CHUNK = 262144;

    private const HEADER = '/^\[(?<time>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)\] (?<env>[\w.-]+)\.(?<level>[A-Za-z]+): ?(?<message>.*)$/';

    /** @var array<string, list<int>> */
    protected array $searches = [];

    /**
     * @param  list<string>  $patterns  glob patterns relative to $root
     * @param  string|null  $indexDir  where entry indexes are kept; null keeps them in memory only
     */
    public function __construct(
        protected string $root,
        protected array $patterns = ['*.log', '**/*.log'],
        protected ?string $indexDir = null,
    ) {}

    /**
     * @return list<array{name: string, size: int, modified: int}>
     */
    public function files(): array
    {
        $root = realpath($this->root);

        if ($root === false) {
            return [];
        }

        $found = [];

        foreach ($this->patterns as $pattern) {
            foreach ($this->glob($root, $pattern) as $path) {
                $real = realpath($path);

                if ($real === false || ! is_file($real) || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $name = substr($real, strlen($root) + 1);
                $found[$name] = ['name' => $name, 'size' => (int) filesize($real), 'modified' => (int) filemtime($real)];
            }
        }

        usort($found, fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return array_values($found);
    }

    /**
     * Absolute path of a listed file, or null when the name is not one of
     * the files() results (this is the only way a file gets opened).
     */
    public function path(string $name): ?string
    {
        foreach ($this->files() as $file) {
            if ($file['name'] === $name) {
                return $this->root.DIRECTORY_SEPARATOR.$name;
            }
        }

        return null;
    }

    /**
     * Newest-first page of entries, with the total so the page can show
     * page numbers.
     *
     * @param  list<string>  $levels  empty = every level
     * @return array{entries: list<LogEntry>, total: int, has_more: bool, levels: array<string, int>}
     */
    public function entries(string $name, int $page = 1, int $perPage = 25, array $levels = [], ?string $search = null): array
    {
        $path = $this->path($name);

        if ($path === null) {
            return ['entries' => [], 'total' => 0, 'has_more' => false, 'levels' => []];
        }

        $index = (new LogIndex($path, $this->indexDir))->refresh();
        $search = $search !== null && trim($search) !== '' ? trim($search) : null;
        $skip = max(0, $page - 1) * $perPage;

        // Entry numbers (positions in the index), newest first: only the
        // page's own, straight from the index, unless searching.
        if ($search === null) {
            ['ids' => $ids, 'total' => $total] = $index->page($levels, $skip, $perPage);
        } else {
            $matches = $this->idsBySearch($path, $index, $levels, $search);
            $total = count($matches);
            $ids = array_slice($matches, $skip, $perPage);
        }

        return [
            'entries' => $this->read($path, $index, $ids),
            'total' => $total,
            'has_more' => $page * $perPage < $total,
            'levels' => $index->levelCounts(),
        ];
    }

    /**
     * Error-level entries since a moment, newest first, for the Overview:
     * how many there are (up to $cap) and the newest one. Walks the level
     * index backwards, so it reads only error entries, never the whole file.
     *
     * @return array{count: int, capped: bool, latest: ?LogEntry}
     */
    public function errorsSince(string $name, \DateTimeInterface $since, int $cap = 500): array
    {
        $path = $this->path($name);

        if ($path === null) {
            return ['count' => 0, 'capped' => false, 'latest' => null];
        }

        $index = (new LogIndex($path, $this->indexDir))->refresh();
        $levels = ['emergency', 'alert', 'critical', 'error'];
        $count = 0;
        $latest = null;

        for ($skip = 0; $count < $cap; $skip += 50) {
            $entries = $this->read($path, $index, $index->page($levels, $skip, 50)['ids']);

            foreach ($entries as $entry) {
                $latest ??= $entry;

                if ($entry->time === null || $entry->time->lt($since)) {
                    return ['count' => $count, 'capped' => false, 'latest' => $latest];
                }

                if (++$count >= $cap) {
                    return ['count' => $count, 'capped' => true, 'latest' => $latest];
                }
            }

            if (count($entries) < 50) {
                break;
            }
        }

        return ['count' => $count, 'capped' => false, 'latest' => $latest];
    }

    /**
     * Search has to read every entry. Matches are remembered briefly, so
     * moving between pages of the same search doesn't read the file again.
     *
     * @param  list<string>  $levels
     * @return list<int>
     */
    protected function idsBySearch(string $path, LogIndex $index, array $levels, string $search): array
    {
        $key = md5(implode('|', [$path, $index->scanned, implode(',', $levels), $search]));

        if (isset($this->searches[$key])) {
            return $this->searches[$key];
        }

        try {
            $cached = Cache::get('debugger:log-search:'.$key);
        } catch (Throwable) {
            $cached = null;
        }

        if (is_array($cached)) {
            return $this->searches[$key] = $cached;
        }

        $ids = [];
        $id = $index->count();

        foreach ($this->readBackwards($path, $index->scanned) as $entry) {
            $id--;

            if ($levels !== [] && ! in_array($entry->level, $levels, true)) {
                continue;
            }

            if (Str::contains($entry->message."\n".$entry->body, $search, true)) {
                $ids[] = $id;
            }
        }

        // Small result lists only: a search matching most of a huge file
        // isn't worth storing.
        if (count($ids) <= 50000) {
            try {
                Cache::put('debugger:log-search:'.$key, $ids, 120);
            } catch (Throwable) {
                //
            }
        }

        return $this->searches[$key] = $ids;
    }

    /**
     * Read just the given entries, by their byte offsets.
     *
     * @param  list<int>  $ids
     * @return list<LogEntry>
     */
    protected function read(string $path, LogIndex $index, array $ids): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $entries = [];

        foreach ($index->ranges($ids) as [$start, $end]) {
            fseek($handle, $start);
            $text = (string) fread($handle, max(0, $end - $start));
            $lines = preg_split('/\r\n|\n|\r/', rtrim($text, "\r\n")) ?: [];
            $pending = array_reverse(array_slice($lines, 1));

            if ($entry = $this->collect($lines[0] ?? '', $pending)) {
                $entries[] = $entry;
            }
        }

        fclose($handle);

        return $entries;
    }

    /**
     * @return \Generator<int, LogEntry>
     */
    public function readBackwards(string $path, ?int $end = null): \Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            $position = $end ?? (int) filesize($path);
            $carry = '';
            $pending = [];

            while ($position > 0) {
                $length = min(self::CHUNK, $position);
                $position -= $length;
                fseek($handle, $position);
                $buffer = fread($handle, $length).$carry;

                $lines = preg_split('/\r\n|\n|\r/', $buffer) ?: [];
                // The first line may continue in the previous chunk.
                $carry = $position > 0 ? (string) array_shift($lines) : '';

                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    $entry = $this->collect($lines[$i], $pending);

                    if ($entry !== null) {
                        yield $entry;
                    }
                }
            }

            if ($carry !== '') {
                $entry = $this->collect($carry, $pending);

                if ($entry !== null) {
                    yield $entry;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Feed one line (walking backwards). Returns an entry once its header
     * line is reached; continuation lines are kept until then.
     *
     * @param  list<string>  $pending
     */
    protected function collect(string $line, array &$pending): ?LogEntry
    {
        if (! preg_match(self::HEADER, $line, $match)) {
            $pending[] = $line;

            return null;
        }

        $body = trim(implode("\n", array_reverse($pending)), "\n");
        $pending = [];

        return new LogEntry(
            time: $this->time($match['time']),
            environment: $match['env'],
            level: strtolower($match['level']),
            message: $match['message'],
            body: $body,
        );
    }

    protected function time(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * glob() has no "**", so expand it by walking directories.
     *
     * @return list<string>
     */
    protected function glob(string $root, string $pattern): array
    {
        if (! str_contains($pattern, '**')) {
            return glob($root.DIRECTORY_SEPARATOR.$pattern) ?: [];
        }

        $suffix = ltrim(str_replace('**', '', $pattern), '/');
        $matches = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $matches = [...$matches, ...(glob($item->getPathname().DIRECTORY_SEPARATOR.$suffix) ?: [])];
            }
        }

        return $matches;
    }
}
