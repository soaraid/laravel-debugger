<?php

namespace Soara\Debugger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Soara\Debugger\Impersonation\Impersonator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the "You're signed in as …" bar to the host app's HTML pages while
 * impersonating. JSON, redirects, downloads and streamed responses are left
 * alone. Turn it off with debugger.impersonation.show_bar and use
 * Debugger::impersonation() to build your own banner instead.
 */
class InjectImpersonationBar
{
    public function __construct(protected Impersonator $impersonator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('debugger.impersonation.show_bar', true) || ! $this->impersonator->active($request)) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        $content = $response->getContent();

        if (
            $response->isRedirection()
            // A plain string response has no Content-Type yet at this point.
            || ($type !== '' && ! str_contains($type, 'text/html'))
            || $content === false
            || $request->header('X-Inertia')
            || ! preg_match('/<body\b[^>]*>/i', $content, $match, PREG_OFFSET_CAPTURE)
        ) {
            return $response;
        }

        $bar = view('debugger::impersonation-bar', ['impersonation' => $this->impersonator->current($request)])->render();
        $position = $match[0][1] + strlen($match[0][0]);

        $response->setContent(substr($content, 0, $position).$bar.substr($content, $position));

        return $response;
    }
}
