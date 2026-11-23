<?php

use Illuminate\Support\Facades\Route;
use Soara\Debugger\Http\Controllers\ImpersonationController;

// Outside the debugger gate on purpose: the user being impersonated usually
// has no access to the debugger, but must still be able to switch back.
Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
