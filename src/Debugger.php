<?php

namespace Soara\Debugger;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class Debugger
{
    /**
     * Sections in the order they appear in the header.
     *
     * @var array<string, string>
     */
    public const MODULES = [
        'overview' => 'Overview',
        'logs' => 'Logs',
        'queue' => 'Queue',
        'scheduler' => 'Scheduler',
        'cache' => 'Cache',
        'mail' => 'Mail',
        'storage' => 'Storage',
        'connections' => 'Connections',
        'users' => 'Users',
    ];

    protected static ?Closure $authUsing = null;

    protected static ?Closure $userRoleUsing = null;

    protected static ?Closure $userQueryUsing = null;

    /**
     * Decide who may open the debugger, e.g.
     * Debugger::auth(fn (Request $request) => $request->user()?->isDeveloper()).
     */
    public static function auth(?Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * Show a role next to each user on the Users page, e.g.
     * Debugger::userRole(fn ($user) => $user->getRoleNames()->first()).
     */
    public static function userRole(?Closure $callback): void
    {
        static::$userRoleUsing = $callback;
    }

    /**
     * Limit which users the Users page lists, e.g.
     * Debugger::userQuery(fn ($query) => $query->where('is_active', true)).
     */
    public static function userQuery(?Closure $callback): void
    {
        static::$userQueryUsing = $callback;
    }

    public static function userRoleResolver(): ?Closure
    {
        return static::$userRoleUsing;
    }

    public static function userQueryScope(): ?Closure
    {
        return static::$userQueryUsing;
    }

    public static function check(Request $request): bool
    {
        if (static::$authUsing !== null) {
            return (bool) (static::$authUsing)($request);
        }

        if (Gate::has('viewDebugger')) {
            return Gate::forUser($request->user())->check('viewDebugger');
        }

        return app()->environment('local');
    }

    public static function readOnly(): bool
    {
        $value = config('debugger.read_only');

        if ($value === null || $value === '') {
            return app()->isProduction();
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return array<string, string> enabled modules, key => label
     */
    public static function modules(): array
    {
        $enabled = (array) config('debugger.modules', []);

        return array_filter(
            self::MODULES,
            // A section missing from the config is on, so new sections show
            // up in apps that published an older copy of the config.
            fn (string $key): bool => (bool) ($enabled[$key] ?? true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The running impersonation, for apps that show their own banner:
     * ['id' => original user id, 'name' => original user name, 'target' => impersonated user name] or null.
     *
     * @return array<string, mixed>|null
     */
    public static function impersonation(?Request $request = null): ?array
    {
        return app(Impersonation\Impersonator::class)->current($request ?? request());
    }

    public static function moduleEnabled(string $module): bool
    {
        return array_key_exists($module, static::modules());
    }
}
