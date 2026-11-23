<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Soara\Debugger\Impersonation\Impersonator;
use Workbench\App\Models\User;

// Workbench-only routes for trying the package locally.

// A stand-in for the host app's home page, so the impersonation bar has a
// page to appear on.
Route::get('/', function () {
    $user = Auth::user();

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Workbench app</title></head>'
        .'<body style="margin:0;font-family:system-ui,sans-serif;background:#f7faf8;color:#161c19">'
        .'<main style="max-width:880px;margin:0 auto;padding:48px 32px">'
        .'<p style="color:#666e69;font-size:13px">Workbench app (stands in for the host application)</p>'
        .'<h1 style="font-size:22px;margin:8px 0 16px">'.($user ? 'Hello, '.e($user->name) : 'Not signed in').'</h1>'
        .'<p><a href="/workbench/login">Sign in as Dev Admin</a> · <a href="/debugger">Open the debugger</a></p>'
        .'</main></body></html>';
});

// Sign in as the first user (Dev Admin, a developer) and open the Users page.
Route::get('/workbench/login', function () {
    Auth::login(User::query()->orderBy('id')->firstOrFail());

    return redirect('/debugger/users');
});

// One-click demo of the impersonation bar: sign in as Dev Admin, then log in as
// Andi Pratama and land on the app's home page.
Route::get('/workbench/impersonate-demo', function (Request $request) {
    Auth::login(User::query()->orderBy('id')->firstOrFail());
    $andi = User::query()->where('email', 'andi@example.com')->firstOrFail();
    app(Impersonator::class)->start($request, (string) $andi->id);

    return redirect('/');
});
