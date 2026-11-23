<?php

namespace Soara\Debugger\Logs;

/**
 * Where each entry of a log file starts and what level it has.
 *
 * Built by reading the file forwards once; after that only the part that
 * was appended is read. Stored as three small files, append-only:
 *
 * - .off  one 8-byte offset per entry, so entry N's offset is a single
 *         seek to N * 8 (no need to load the whole index for a page)
 * - .lvl  one byte per entry for its level, so per-level counts are one
 *         count_chars() and a level filter walks bytes, not entries
 * - .json scan position and a fingerprint of the file's start
 *
 * With no store directory (tests) the same data is kept in memory.
 */
class LogIndex
{
    private const VERSION = 2;

    private const CHUNK = 1048576;

    private const HEADER = '/^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*\] [\w.-]+\.([A-Za-z]+):/';

    /** One byte per level in the .lvl file. */
    public const CODES = [
        'emergency' => 'M', 'alert' => 'A', 'critical' => 'C', 'error' => 'E',
        'warning' => 'W', 'notice' => 'N', 'info' => 'I', 'debug' => 'D',
    ];

    /** Bytes read so far; entries after this point are not indexed yet. */
    public int $scanned = 0;

    protected int $count = 0;

    protected string $head = '';

    /** In-memory copies (always for levels; offsets only without a store). */
    protected string $levels = '';

    protected string $offsets = '';

    public function __construct(protected string $path, protected ?string $storeDir = null) {}

    /**
     * Load the stored state and index whatever was appended since.
     */
    public function refresh(): static
    {
        $this->load();

        $size = (int) @filesize($this->path);
        $head = $this->fingerprint();

        // Truncated, rotated or replaced: start over.
        if ($size < $this->scanned || $head !== $this->head) {
            $this->reset($head);
        }

        if ($size > $this->scanned) {
            [$offsets, $levels] = $this->scan($size);
            $this->append($offsets, $levels);
        }

        return $this;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return array<string, int> entries per level
     */
    public function levelCounts(): array
    {
        $names = array_flip(self::CODES);
        $counts = [];

        foreach (count_chars($this->levels, 1) as $byte => $n) {
            $counts[$names[chr($byte)] ?? 'other'] = $n;
        }

        return $counts;
    }

    /**
     * Entry numbers for one page, newest first, keeping only $levels.
     *
     * @param  list<string>  $levels  empty = every level
     * @return array{ids: list<int>, total: int}
     */
    public function page(array $levels, int $skip, int $take): array
    {
        if ($levels === []) {
            $first = $this->count - 1 - $skip;

            return [
                'ids' => $first < 0 ? [] : range($first, max(0, $first - $take + 1)),
                'total' => $this->count,
            ];
        }

        $codes = array_values(array_filter(array_map(fn (string $l): ?string => self::CODES[$l] ?? null, $levels)));
        $counts = count_chars($this->levels, 1);
        $total = array_sum(array_map(fn (string $c): int => $counts[ord($c)] ?? 0, $codes));
        $ids = [];
        $position = strlen($this->levels);

        // Walk backwards from the newest entry, jumping between matches.
        while (count($ids) < $take && $position > 0) {
            $found = -1;

            foreach ($codes as $code) {
                $at = strrpos($this->levels, $code, $position - strlen($this->levels) - 1);
                $found = max($found, $at === false ? -1 : $at);
            }

            if ($found < 0) {
                break;
            }

            $skip > 0 ? $skip-- : $ids[] = $found;
            $position = $found;
        }

        return ['ids' => $ids, 'total' => $total];
    }

    /**
     * Byte ranges [start, end) of the given entries.
     *
     * @param  list<int>  $ids
     * @return array<int, array{int, int}>
     */
    public function ranges(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // One read covering every offset the page needs (plus the next one, for the end).
        $low = min($ids);
        $high = min(max($ids) + 1, $this->count - 1);
        $values = $this->offsetsBetween($low, $high);
        $ranges = [];

        foreach ($ids as $id) {
            $start = $values[$id - $low];
            $end = $id + 1 < $this->count ? $values[$id + 1 - $low] : $this->scanned;
            $ranges[$id] = [$start, $end];
        }

        return $ranges;
    }

    /**
     * @return list<int>
     */
    protected function offsetsBetween(int $low, int $high): array
    {
        $length = ($high - $low + 1) * 8;

        if ($this->storeDir === null) {
            $bytes = substr($this->offsets, $low * 8, $length);
        } else {
            $handle = @fopen($this->file('off'), 'rb');

            if ($handle === false) {
                return [];
            }

            fseek($handle, $low * 8);
            $bytes = (string) fread($handle, $length);
            fclose($handle);
        }

        return array_values(unpack('P*', $bytes) ?: []);
    }

    /**
     * @return array{0: list<int>, 1: string} new offsets and their level codes
     */
    protected function scan(int $size): array
    {
        $handle = @fopen($this->path, 'rb');
        $offsets = [];
        $levels = '';

        if ($handle === false) {
            return [$offsets, $levels];
        }

        fseek($handle, $this->scanned);
        $position = $this->scanned;
        $carry = '';

        while ($position < $size && ! feof($handle)) {
            $chunk = fread($handle, (int) min(self::CHUNK, $size - $position));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $buffer = $carry.$chunk;
            $start = $position - strlen($carry);
            $position += strlen($chunk);
            $offset = 0;

            while (($newline = strpos($buffer, "\n", $offset)) !== false) {
                if ($buffer[$offset] === '[' && preg_match(self::HEADER, substr($buffer, $offset, min(160, $newline - $offset)), $match)) {
                    $offsets[] = $start + $offset;
                    $levels .= self::CODES[strtolower($match[1])] ?? 'O';
                }

                $offset = $newline + 1;
            }

            $carry = substr($buffer, $offset);
            // Only complete lines count as scanned; the rest is read next time.
            $this->scanned = $start + $offset;
        }

        // A last line without a newline is complete once the file stops there.
        if ($carry !== '' && $position >= $size) {
            if ($carry[0] === '[' && preg_match(self::HEADER, substr($carry, 0, 160), $match)) {
                $offsets[] = $this->scanned;
                $levels .= self::CODES[strtolower($match[1])] ?? 'O';
            }

            $this->scanned = $size;
        }

        fclose($handle);

        return [$offsets, $levels];
    }

    /**
     * @param  list<int>  $offsets
     */
    protected function append(array $offsets, string $levels): void
    {
        $packed = $offsets === [] ? '' : pack('P*', ...$offsets);
        $this->count += count($offsets);
        $this->levels .= $levels;

        if ($this->storeDir === null) {
            $this->offsets .= $packed;

            return;
        }

        if ($packed !== '') {
            file_put_contents($this->file('off'), $packed, FILE_APPEND | LOCK_EX);
            file_put_contents($this->file('lvl'), $levels, FILE_APPEND | LOCK_EX);
        }

        $this->saveMeta();
    }

    protected function reset(string $head): void
    {
        $this->scanned = 0;
        $this->count = 0;
        $this->levels = '';
        $this->offsets = '';
        $this->head = $head;

        if ($this->storeDir !== null) {
            if (! is_dir($this->storeDir)) {
                @mkdir($this->storeDir, 0775, true);
            }

            @file_put_contents($this->file('off'), '');
            @file_put_contents($this->file('lvl'), '');
            $this->saveMeta();
        }
    }

    protected function load(): void
    {
        if ($this->storeDir === null || ! is_file($this->file('json'))) {
            return;
        }

        $meta = json_decode((string) @file_get_contents($this->file('json')), true);

        if (! is_array($meta) || ($meta['version'] ?? null) !== self::VERSION) {
            return;
        }

        $levels = (string) @file_get_contents($this->file('lvl'));
        $offsetBytes = (int) @filesize($this->file('off'));

        // Both files must agree with the metadata, or the index is rebuilt.
        if (strlen($levels) !== $meta['count'] || $offsetBytes !== $meta['count'] * 8) {
            return;
        }

        $this->scanned = (int) $meta['scanned'];
        $this->count = (int) $meta['count'];
        $this->head = (string) $meta['head'];
        $this->levels = $levels;
    }

    protected function saveMeta(): void
    {
        @file_put_contents($this->file('json'), json_encode([
            'version' => self::VERSION,
            'scanned' => $this->scanned,
            'count' => $this->count,
            'head' => $this->head,
        ]), LOCK_EX);
    }

    /**
     * The first bytes of the file, to notice when it was replaced.
     */
    protected function fingerprint(): string
    {
        $handle = @fopen($this->path, 'rb');
        $head = $handle ? (string) fread($handle, 256) : '';

        if ($handle) {
            fclose($handle);
        }

        return md5($head);
    }

    protected function file(string $extension): string
    {
        return rtrim((string) $this->storeDir, '/').'/'.md5((string) realpath($this->path)).'.'.$extension;
    }
}
