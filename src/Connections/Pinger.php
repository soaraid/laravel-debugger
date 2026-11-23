<?php

namespace Soara\Debugger\Connections;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Soara\Debugger\Support\Redactor;
use Throwable;

/**
 * Pings the external APIs listed in config('debugger.connections') and keeps
 * the last result of each in the cache.
 */
class Pinger
{
    public const KEY = 'debugger:connections:results';

    /**
     * @return array<string, array{name: string, method: string, url: string, timeout: int}>
     */
    public function services(): array
    {
        $services = [];

        foreach ((array) config('debugger.connections', []) as $key => $service) {
            if (! is_array($service) || empty($service['url'])) {
                continue;
            }

            $services[(string) $key] = [
                'name' => (string) ($service['name'] ?? Str::headline((string) $key)),
                'method' => strtoupper((string) ($service['method'] ?? 'GET')),
                'url' => (string) $service['url'],
                'timeout' => (int) ($service['timeout'] ?? 5),
            ];
        }

        return $services;
    }

    /**
     * @return array<string, mixed>
     */
    public function ping(string $key): array
    {
        $service = (array) config("debugger.connections.{$key}", []);
        $config = $this->services()[$key] ?? throw new \InvalidArgumentException("Unknown connection [{$key}].");
        $start = microtime(true);

        try {
            $response = Http::withHeaders((array) ($service['headers'] ?? []))
                ->timeout($config['timeout'])
                ->connectTimeout(min($config['timeout'], 5))
                ->send($config['method'], $config['url'], array_filter(['json' => $service['body'] ?? null]));

            $status = $response->status();
            $result = [
                'status' => $status,
                'label' => trim($status.' '.$response->reason()),
                'tone' => $status < 400 ? 'ok' : (in_array($status, [401, 403], true) ? 'warning' : 'danger'),
                'ms' => round((microtime(true) - $start) * 1000),
                'error' => $status < 400 ? null : Redactor::clean(Str::limit(trim($response->body()), 300)),
                'hint' => $this->hintForStatus($status),
            ];
        } catch (ConnectionException $e) {
            $timedOut = str_contains(strtolower($e->getMessage()), 'timed out');
            $result = [
                'status' => null,
                'label' => $timedOut ? 'Timeout' : 'Unreachable',
                'tone' => 'danger',
                'ms' => null,
                'error' => Redactor::clean($e->getMessage()),
                'hint' => $timedOut
                    ? "No response within {$config['timeout']} s. Check the URL, and whether their side allowlists this server's IP."
                    : (str_contains(strtolower($e->getMessage()), 'resolve') ? "The host name couldn't be resolved. Check the URL." : 'The server refused the connection. Check the URL and port.'),
            ];
        } catch (Throwable $e) {
            $result = ['status' => null, 'label' => 'Error', 'tone' => 'danger', 'ms' => null, 'error' => Redactor::clean($e->getMessage()), 'hint' => null];
        }

        $result['checked_at'] = CarbonImmutable::now()->toIso8601String();
        $this->store($key, $result);

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function results(): array
    {
        try {
            return (array) Cache::get(self::KEY, []);
        } catch (Throwable) {
            return [];
        }
    }

    protected function store(string $key, array $result): void
    {
        try {
            Cache::forever(self::KEY, [...$this->results(), $key => $result]);
        } catch (Throwable) {
            //
        }
    }

    protected function hintForStatus(int $status): ?string
    {
        return match (true) {
            $status < 400 => null,
            in_array($status, [401, 403], true) => 'The server is reachable, but it rejected the credentials. Check the token in .env.',
            $status === 404 => 'The server is reachable, but this URL was not found. Check the path.',
            $status === 429 => 'The server is reachable, but it is rate limiting this app.',
            $status >= 500 => 'Their server returned an error. Try again later or check their status page.',
            default => 'The server answered with an error. Check the method, URL and headers.',
        };
    }
}
