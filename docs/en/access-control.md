---
title: Access control
description: Who may open the debugger and who may log in as another user.
---

The debugger checks access on every page and every API call. There are three ways to set it, and the order matters.

## Order of checks

1. If you call `Debugger::auth()`, that callback decides. The gate is ignored.
2. If not, and the `viewDebugger` gate exists, the gate decides. This applies in every environment, local included.
3. If neither exists, the debugger only opens in the `local` environment.

Anyone refused gets a 403.

## The viewDebugger gate

The most common way. Put it in `AppServiceProvider`:

```php title="app/Providers/AppServiceProvider.php"
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewDebugger', fn ($user) => in_array($user->email, [
        'dev@yourapp.com',
    ]));
}
```

If your app uses permissions, for example spatie/laravel-permission:

```php title="app/Providers/AppServiceProvider.php"
Gate::define('viewDebugger', fn ($user) => $user->can('Developer Tools'));
```

> [!NOTE]
> The gate receives the signed-in user. To send guests straight to the login page, add `auth` to the `middleware` config. See [Configuration](./configuration.md#path-and-routes).

## Debugger::auth()

For rules that don't fit a gate, for example by IP or header. The callback receives the request:

```php title="app/Providers/AppServiceProvider.php"
use Illuminate\Http\Request;
use Soara\Debugger\Debugger;

public function boot(): void
{
    Debugger::auth(fn (Request $request) => $request->user()?->is_developer);
}
```

## Impersonation

Logging in as another user on the [Users](./users.md) page has two gates of its own. Both are optional.

- `impersonate`: who may use Log in as. Without this gate, everyone who can open the debugger may use it.
- `beImpersonated`: which users may be logged in as. It receives the signed-in user and the target user. A refused user shows "Protected" and no button.

```php title="app/Providers/AppServiceProvider.php"
Gate::define('impersonate', fn ($user) => $user->can('User.Impersonate'));

// Developer accounts can't be logged in as by anyone else.
Gate::define('beImpersonated', fn ($user, $target) => ! $target->hasRole('Developer'));
```

> [!WARNING]
> Without `beImpersonated`, anyone who can open the debugger can log in as any user, admins included. Define this gate in production.

A few limits always apply too: you can't log in as yourself, you can't nest impersonation, and every start and stop is written to the audit log.
