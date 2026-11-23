<?php

namespace Soara\Debugger\Storage;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Test uploads on the configured disk, inside one folder. Files are named
 * by the package and addressed by file name only, so nothing outside the
 * folder can be read or deleted.
 */
class StorageTester
{
    /**
     * Shown inline only for these extensions, with a fixed MIME type. The
     * type never comes from the file's contents, so an HTML file renamed
     * to .txt is still served as text/plain.
     */
    public const PREVIEW = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=utf-8',
        'log' => 'text/plain; charset=utf-8',
        'csv' => 'text/plain; charset=utf-8',
        'json' => 'text/plain; charset=utf-8',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
    ];

    public function diskName(): string
    {
        return (string) config('debugger.storage.disk', 'local');
    }

    public function folder(): string
    {
        return trim((string) config('debugger.storage.folder', 'debugger'), '/');
    }

    /**
     * @return list<array{name: string, size: int, modified: ?CarbonImmutable}>
     */
    public function files(): array
    {
        $disk = $this->disk();

        $files = array_map(function (string $path) use ($disk): array {
            $modified = null;

            try {
                $modified = CarbonImmutable::createFromTimestamp($disk->lastModified($path));
            } catch (Throwable) {
                //
            }

            return ['name' => basename($path), 'size' => (int) $disk->size($path), 'modified' => $modified];
        }, $disk->files($this->folder()));

        usort($files, fn (array $a, array $b): int => ($b['modified']?->getTimestamp() ?? 0) <=> ($a['modified']?->getTimestamp() ?? 0));

        return $files;
    }

    public function upload(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $name = $base.($extension !== '' ? '.'.$extension : '');

        if ($this->disk()->exists($this->path($name))) {
            $name = $base.'-'.Str::lower(Str::random(6)).($extension !== '' ? '.'.$extension : '');
        }

        // Private on purpose: never reachable through a public URL.
        $this->disk()->putFileAs($this->folder(), $file, $name, ['visibility' => 'private']);

        return $name;
    }

    public function delete(string $name): void
    {
        $path = $this->existing($name);

        if (! $this->disk()->delete($path)) {
            throw new \RuntimeException("The disk didn't delete {$name}.");
        }
    }

    public function response(string $name): StreamedResponse
    {
        $path = $this->existing($name);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $type = self::PREVIEW[$extension] ?? null;

        $response = $type !== null
            ? $this->disk()->response($path, $name, ['Content-Type' => $type], 'inline')
            : $this->disk()->download($path, $name, ['Content-Type' => 'application/octet-stream']);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Even an inline file can't run scripts or reach the app's origin.
        $response->headers->set('Content-Security-Policy', 'sandbox');

        return $response;
    }

    /**
     * Write, read back and delete a small file on each disk.
     *
     * @return list<array{disk: string, ok: bool, ms: float, error: ?string}>
     */
    public function probe(): array
    {
        $disks = array_values(array_unique([$this->diskName(), 'local']));

        return array_map(function (string $name): array {
            $start = microtime(true);
            $path = $this->folder().'/.probe-'.Str::lower(Str::random(10));

            try {
                $disk = Storage::disk($name);
                $disk->put($path, 'ok');
                $ok = $disk->get($path) === 'ok';
                $disk->delete($path);

                return ['disk' => $name, 'ok' => $ok, 'ms' => round((microtime(true) - $start) * 1000, 1), 'error' => $ok ? null : 'read back a different value'];
            } catch (Throwable $e) {
                return ['disk' => $name, 'ok' => false, 'ms' => round((microtime(true) - $start) * 1000, 1), 'error' => $e->getMessage()];
            }
        }, $disks);
    }

    public function freeSpace(): ?float
    {
        $free = @disk_free_space(storage_path());

        return $free === false ? null : (float) $free;
    }

    public function maxKilobytes(): int
    {
        $ini = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $config = (int) config('debugger.storage.max_kb', 51200);

        return $ini > 0 ? min($config, intdiv($ini, 1024)) : $config;
    }

    protected function existing(string $name): string
    {
        if ($name === '' || $name !== basename($name) || str_starts_with($name, '.')) {
            throw new InvalidArgumentException('That file name is not allowed.');
        }

        $path = $this->path($name);

        if (! $this->disk()->exists($path)) {
            throw new InvalidArgumentException("{$name} doesn't exist any more.");
        }

        return $path;
    }

    protected function path(string $name): string
    {
        return $this->folder().'/'.$name;
    }

    protected function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    protected function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
