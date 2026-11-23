<?php

namespace Soara\Debugger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Soara\Debugger\Debugger;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to every route that changes something. Pages stay readable in
 * read-only mode; actions are refused.
 */
class EnsureWritable
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Debugger::readOnly(), 403, __('debugger::messages.read_only'));

        return $next($request);
    }
}
