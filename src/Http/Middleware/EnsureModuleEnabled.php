<?php

namespace Soara\Debugger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Soara\Debugger\Debugger;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(Debugger::moduleEnabled($module), 404);

        return $next($request);
    }
}
