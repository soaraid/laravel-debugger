<?php

namespace Soara\Debugger\Cache;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class CacheActions
{
    /**
     * @var array<string, array{command: string, title: string, label: string}>
     */
    public const ACTIONS = [
        'cache' => ['command' => 'cache:clear', 'title' => 'Application cache', 'label' => 'Clear'],
        'config' => ['command' => 'config:clear', 'title' => 'Config cache', 'label' => 'Clear'],
        'route' => ['command' => 'route:clear', 'title' => 'Route cache', 'label' => 'Clear'],
        'view' => ['command' => 'view:clear', 'title' => 'Compiled views', 'label' => 'Clear'],
        'clear' => ['command' => 'optimize:clear', 'title' => 'Everything', 'label' => 'Clear'],
        'optimize' => ['command' => 'optimize', 'title' => 'Rebuild caches', 'label' => 'Rebuild'],
    ];

    public function run(string $action): string
    {
        $definition = self::ACTIONS[$action] ?? throw new RuntimeException("Unknown cache action [{$action}].");

        if (Artisan::call($definition['command']) !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: "{$definition['command']} failed.");
        }

        return $definition['command'];
    }

    /**
     * @return array<string, string>
     */
    public function status(): array
    {
        $views = glob(rtrim((string) config('view.compiled', storage_path('framework/views')), '/').'/*.php') ?: [];

        return [
            'Store' => (string) config('cache.default'),
            'Config' => app()->configurationIsCached() ? 'cached' : 'not cached',
            'Routes' => app()->routesAreCached() ? 'cached' : 'not cached',
            'Events' => app()->eventsAreCached() ? 'cached' : 'not cached',
            'Views' => count($views).' compiled',
            'Session' => (string) config('session.driver'),
        ];
    }
}
