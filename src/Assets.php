<?php

namespace Soara\Debugger;

use RuntimeException;

/**
 * Resolves the built CSS/JS in dist/ (written by `npm run build`) through the
 * Vite manifest, so file names carry a content hash and can be cached forever.
 */
class Assets
{
    public const ENTRY_CSS = 'resources/css/app.css';

    public const ENTRY_JS = 'resources/js/app.js';

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $manifest = null;

    public function __construct(protected string $distPath) {}

    public function url(string $entry): string
    {
        return route('debugger.asset', ['path' => $this->file($entry)]);
    }

    public function file(string $entry): string
    {
        $file = $this->manifest()[$entry]['file'] ?? null;

        if (! is_string($file)) {
            throw new RuntimeException("Debugger asset [{$entry}] is missing. Run `npm run build` in the package.");
        }

        return $file;
    }

    /**
     * URLs of the Latin font files, to preload so text renders in Geist on
     * the first paint instead of swapping in later.
     *
     * @return list<string>
     */
    public function fonts(): array
    {
        $files = (array) ($this->manifest()[self::ENTRY_CSS]['assets'] ?? []);

        return array_values(array_map(
            fn (string $file): string => route('debugger.asset', ['path' => $file]),
            array_filter($files, fn (string $file): bool => (bool) preg_match('/-latin-wght-normal-[\w-]+\.woff2$/', $file)),
        ));
    }

    /**
     * Absolute path of a file inside dist/, or null when it does not exist or
     * points outside dist/.
     */
    public function path(string $file): ?string
    {
        $root = realpath($this->distPath);
        $path = realpath($this->distPath.'/'.$file);

        if ($root === false || $path === false || ! is_file($path)) {
            return null;
        }

        return str_starts_with($path, $root.DIRECTORY_SEPARATOR) ? $path : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $file = $this->distPath.'/.vite/manifest.json';
        $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return $this->manifest = is_array($json) ? $json : [];
    }
}
