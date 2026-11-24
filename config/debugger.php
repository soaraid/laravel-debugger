<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the debugger
    |--------------------------------------------------------------------------
    |
    | When false, no debugger routes are registered at all.
    |
    */

    'enabled' => env('DEBUGGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Where the debugger lives
    |--------------------------------------------------------------------------
    */

    'path' => env('DEBUGGER_PATH', 'debugger'),

    'domain' => env('DEBUGGER_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Route middleware
    |--------------------------------------------------------------------------
    |
    | Access is checked after these run, through Debugger::auth() or the
    | "viewDebugger" gate. Without either, only the local environment is
    | allowed in.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Read-only mode
    |--------------------------------------------------------------------------
    |
    | Blocks every action (clear or delete a log, retry, delete, clear cache,
    | send, upload, ping, impersonate) and keeps the pages readable. null means "on in
    | production, off everywhere else".
    |
    */

    'read_only' => env('DEBUGGER_READ_ONLY'),

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    */

    'modules' => [
        'overview' => true,
        'logs' => true,
        'queue' => true,
        'scheduler' => true,
        'cache' => true,
        'mail' => true,
        'storage' => true,
        'connections' => true,
        'users' => true,
    ],

    'logs' => [
        // Glob patterns, relative to storage/logs. Files outside storage/logs are never read.
        'paths' => ['*.log', '**/*.log'],
        'per_page' => 25,
    ],

    'scheduler' => [
        'retention_days' => 7,
        'late_after_minutes' => 3,
        // Adds `debugger:prune` to the schedule (daily) to drop old runs and audit entries.
        'prune' => true,
    ],

    'audit' => [
        'retention_days' => 90,
    ],

    'mail' => [
        // Send every test email here instead of the address typed in the form.
        'override_recipient' => env('DEBUGGER_MAIL_TO'),
    ],

    'storage' => [
        'disk' => env('DEBUGGER_STORAGE_DISK', 'local'),
        'folder' => 'debugger',
        'max_kb' => 51200,
    ],

    /*
    |--------------------------------------------------------------------------
    | External connections
    |--------------------------------------------------------------------------
    |
    | 'midtrans' => [
    |     'name' => 'Midtrans',
    |     'url' => 'https://api.midtrans.com/v2/ping',
    |     'method' => 'GET',
    |     'headers' => ['Authorization' => 'Basic '.env('MIDTRANS_KEY')],
    |     'timeout' => 5,
    | ],
    |
    */

    'connections' => [],

    /*
    |--------------------------------------------------------------------------
    | Users and impersonation
    |--------------------------------------------------------------------------
    |
    | The user model comes from auth.providers for the guard below, so a
    | custom table (app_users) or UUID keys work without extra setup.
    |
    */

    'users' => [
        'guard' => 'web',
        'model' => null,
        'columns' => [
            'name' => 'name',
            'email' => 'email',
            'last_active' => null,
        ],
        'search' => ['name', 'email'],
        // How to show a role and which users to list are set in code, so the
        // config stays cacheable: Debugger::userRole() and Debugger::userQuery().
    ],

    'impersonation' => [
        'enabled' => true,
        'redirect' => '/',
        'show_bar' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Package tables
    |--------------------------------------------------------------------------
    */

    'database' => [
        'connection' => env('DEBUGGER_DB_CONNECTION'),
        'tables' => [
            'schedule_runs' => 'debugger_schedule_runs',
            'audit_logs' => 'debugger_audit_logs',
        ],
    ],

];
