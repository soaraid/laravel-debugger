<?php

use Illuminate\Support\Facades\Route;
use Soara\Debugger\Cache\CacheActions;
use Soara\Debugger\Http\Controllers\Api;
use Soara\Debugger\Http\Controllers\AppController;
use Soara\Debugger\Http\Middleware\EnsureModuleEnabled;
use Soara\Debugger\Http\Middleware\EnsureWritable;

$module = fn (string $name): string => EnsureModuleEnabled::class.':'.$name;

/*
|--------------------------------------------------------------------------
| JSON API used by the Vue app
|--------------------------------------------------------------------------
*/

Route::prefix('api')->name('api.')->group(function () use ($module) {
    // Reading.
    Route::get('/overview', [Api\OverviewController::class, 'index'])->middleware($module('overview'))->name('overview');
    Route::get('/logs', [Api\LogsController::class, 'index'])->middleware($module('logs'))->name('logs');
    Route::get('/logs/info', [Api\LogsController::class, 'info'])->middleware($module('logs'))->name('logs.info');
    Route::get('/queue', [Api\QueueController::class, 'index'])->middleware($module('queue'))->name('queue');
    Route::get('/scheduler', [Api\SchedulerController::class, 'index'])->middleware($module('scheduler'))->name('scheduler');
    Route::get('/cache', [Api\CacheController::class, 'index'])->middleware($module('cache'))->name('cache');
    Route::get('/mail', [Api\MailController::class, 'index'])->middleware($module('mail'))->name('mail');
    Route::get('/storage', [Api\StorageController::class, 'index'])->middleware($module('storage'))->name('storage');
    Route::get('/connections', [Api\ConnectionsController::class, 'index'])->middleware($module('connections'))->name('connections');
    Route::get('/users', [Api\UsersController::class, 'index'])->middleware($module('users'))->name('users');

    // Actions. Every route here changes something, so read-only mode blocks it.
    Route::middleware(EnsureWritable::class)->group(function () use ($module) {
        Route::middleware($module('logs'))->prefix('logs')->name('logs.')->group(function () {
            Route::post('/clear', [Api\LogsController::class, 'clear'])->name('clear');
            Route::delete('/', [Api\LogsController::class, 'destroy'])->name('destroy');
        });

        Route::middleware($module('queue'))->prefix('queue')->name('queue.')->group(function () {
            Route::post('/retry-all', [Api\QueueController::class, 'retryAll'])->name('retry-all');
            Route::post('/{id}/retry', [Api\QueueController::class, 'retry'])->where('id', '[A-Za-z0-9-]+')->name('retry');
            Route::delete('/{id}', [Api\QueueController::class, 'destroy'])->where('id', '[A-Za-z0-9-]+')->name('destroy');
        });

        Route::post('/cache/{action}', [Api\CacheController::class, 'run'])
            ->middleware($module('cache'))
            ->whereIn('action', array_keys(CacheActions::ACTIONS))
            ->name('cache.run');

        Route::post('/mail', [Api\MailController::class, 'send'])->middleware($module('mail'))->name('mail.send');

        Route::middleware($module('storage'))->prefix('storage')->name('storage.')->group(function () {
            Route::post('/', [Api\StorageController::class, 'upload'])->name('upload');
            Route::post('/check', [Api\StorageController::class, 'check'])->name('check');
            Route::delete('/', [Api\StorageController::class, 'destroy'])->name('destroy');
        });

        Route::middleware($module('connections'))->prefix('connections')->name('connections.')->group(function () {
            Route::post('/ping-all', [Api\ConnectionsController::class, 'pingAll'])->name('ping-all');
            Route::post('/{key}/ping', [Api\ConnectionsController::class, 'ping'])->where('key', '[A-Za-z0-9_.-]+')->name('ping');
        });

        Route::post('/users/{id}/impersonate', [Api\UsersController::class, 'impersonate'])
            ->middleware($module('users'))
            ->where('id', '[A-Za-z0-9_-]+')
            ->name('users.impersonate');
    });

    Route::any('/{any?}', fn () => abort(404))->where('any', '.*');
});

// Downloading a log file changes nothing, so it stays available in read-only mode.
Route::get('/logs/download', [Api\LogsController::class, 'download'])->middleware($module('logs'))->name('logs.download');

// Opening a test upload changes nothing, so it stays available in read-only mode.
Route::get('/storage/file', [Api\StorageController::class, 'show'])->middleware($module('storage'))->name('storage.show');

/*
|--------------------------------------------------------------------------
| The app itself: every other URL boots the Vue app
|--------------------------------------------------------------------------
*/

Route::get('/{view?}', AppController::class)->where('view', '.*')->name('index');
