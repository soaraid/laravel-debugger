---
title: Installation
description: Install the package with Composer, run the migration, then open /debugger.
---

Install the package with Composer, run the migration, then open `/debugger`. It takes about a minute.

## Requirements

- PHP 8.2 or newer
- Laravel 12 or 13 (Laravel 13 needs PHP 8.3)

## Install the package

Add it with Composer:

```bash
composer require soara/laravel-debugger
```

> [!NOTE]
> The service provider is registered automatically. You don't need to register it yourself.

## Run the migration

The package keeps the scheduler history and an audit log of actions in two tables.

```bash
php artisan migrate
```

You can change the table names and their connection. See [Configuration](./configuration.md#package-tables).

## Publish the config

Optional. Publish it if you want to change the path, turn modules off, or rename the tables.

```bash
php artisan vendor:publish --tag=debugger-config
```

The file ends up in `config/debugger.php`.

## Open the debugger

Open `/debugger` in your app, for example `http://localhost/debugger`. The first page is the [Overview](./overview.md).

## Give access

Without any setup, the debugger only opens in the `local` environment. To open it anywhere else, define the `viewDebugger` gate in `AppServiceProvider`. Once this gate exists, the gate decides who gets in, local included:

```php title="app/Providers/AppServiceProvider.php"
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewDebugger', fn ($user) => in_array($user->email, [
        'dev@yourapp.com',
    ]));
}
```

Other options are in [Access control](./access-control.md).

> [!WARNING]
> In production, the debugger runs in read-only mode by default: actions like Retry, Clear, and Log in as are turned off. See [Read-only mode](./read-only-mode.md).
