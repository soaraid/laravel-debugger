---
title: Configuration
description: Every setting in config/debugger.php and the env vars you can use.
---

All settings live in `config/debugger.php`. Publish it first if you want to change it:

```bash
php artisan vendor:publish --tag=debugger-config
```

## Env vars

| Env var | Config key | Default |
| --- | --- | --- |
| `DEBUGGER_ENABLED` | `enabled` | `true` |
| `DEBUGGER_PATH` | `path` | `debugger` |
| `DEBUGGER_DOMAIN` | `domain` | empty |
| `DEBUGGER_READ_ONLY` | `read_only` | empty, which means automatic |
| `DEBUGGER_MAIL_TO` | `mail.override_recipient` | empty |
| `DEBUGGER_STORAGE_DISK` | `storage.disk` | `local` |
| `DEBUGGER_DB_CONNECTION` | `database.connection` | the app's default connection |

## Path and routes

- `enabled`: when `false`, no debugger routes are registered at all.
- `path`: where the debugger lives. The default is `debugger`, so `/debugger`.
- `domain`: set it if the debugger should only answer on one domain, for example `ops.yourapp.com`.
- `middleware`: middleware for the debugger pages. The default is `['web']`. Access is checked after this middleware runs. See [Access control](./access-control.md).

```php title="config/debugger.php"
'middleware' => ['web', 'auth'],
```

## Read-only mode

`read_only` turns off every action and keeps the pages readable. Empty (`null`) means on in production and off in every other environment. See [Read-only mode](./read-only-mode.md).

## Modules

Every module can be turned off. A module that is off disappears from the header, and its API answers 404.

```php title="config/debugger.php"
'modules' => [
    'overview' => true,
    'logs' => true,
    'queue' => true,
    'scheduler' => true,
    'cache' => true,
    'mail' => true,
    'storage' => true,
    'connections' => true,
    'users' => false,
],
```

A module that is not in this list counts as on. So a new module in a later version shows up right away, even if your config was published from an older version.

## Per-module settings

### Logs

- `logs.paths`: glob patterns, relative to `storage/logs`. The default is `['*.log', '**/*.log']`. Files outside `storage/logs` are never read.
- `logs.per_page`: entries per page. The default is `25`.

### Scheduler

- `scheduler.retention_days`: how many days of run history to keep. The default is `7`.
- `scheduler.late_after_minutes`: after how many minutes without `schedule:run` the scheduler counts as stopped. The default is `3`.
- `scheduler.prune`: adds `debugger:prune` to the daily schedule to delete old history. The default is `true`.

### Audit log

- `audit.retention_days`: how many days of the action audit log to keep. The default is `90`.

### Mail

- `mail.override_recipient`: every test email goes to this address instead of the one typed in the form. Set it with `DEBUGGER_MAIL_TO`.

### Storage

- `storage.disk`: the disk for test uploads. The default is `local`.
- `storage.folder`: the folder on that disk. The default is `debugger`.
- `storage.max_kb`: the largest file size, in KB. The default is `51200` (50 MB). If PHP's `upload_max_filesize` is smaller, the smaller one is used.

### Connections

- `connections`: the external APIs you can ping. See [Connections](./connections.md).

### Users and impersonation

- `users.guard`: the guard to use. The default is `web`. The user model comes from this guard's provider, so another table or UUID primary keys work as they are.
- `users.model`: set it if the user model differs from the guard's provider.
- `users.columns`: the columns for `name`, `email`, and `last_active`. `last_active` defaults to `null`, which means that column is not shown.
- `users.search`: the columns searched when you type in the search box. The default is `['name', 'email']`.
- `impersonation.enabled`: turns Log in as on or off.
- `impersonation.redirect`: where you land after logging in as another user. The default is `/`.
- `impersonation.show_bar`: shows the "You're signed in as …" bar in the app while impersonating. The default is `true`.

User roles and which users are listed are set in code, not config, so the config stays cacheable. See [Users](./users.md).

## Package tables

```php title="config/debugger.php"
'database' => [
    'connection' => env('DEBUGGER_DB_CONNECTION'),
    'tables' => [
        'schedule_runs' => 'debugger_schedule_runs',
        'audit_logs' => 'debugger_audit_logs',
    ],
],
```

Rename the tables before you run the migration. The migration uses the names from this config.

## Publish views

Optional. The `debugger-views` tag copies the package's Blade views to `resources/views/vendor/debugger`, so you can change them:

```bash
php artisan vendor:publish --tag=debugger-views
```

- `app.blade.php`: the page that loads the debugger.
- `impersonation-bar.blade.php`: the "You're signed in as …" bar during impersonation.
- `mail/test.blade.php`: the body of the test email.

The debugger's own screens ship prebuilt and can't be changed through these views.

> [!NOTE]
> Published views don't change when you update the package. Only publish them if you need to.
