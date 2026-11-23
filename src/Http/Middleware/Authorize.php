<?php

namespace Soara\Debugger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Soara\Debugger\Debugger;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Debugger::check($request), 403);

        return $next($request);
    }
}
