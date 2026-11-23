<?php

use Illuminate\Support\Facades\Route;
use Soara\Debugger\Http\Controllers\AssetController;

// Registered without any middleware (see DebuggerServiceProvider).
Route::get('/assets/{path}', AssetController::class)
    ->where('path', '.+')
    ->name('asset');
