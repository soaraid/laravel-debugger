<?php

namespace Soara\Debugger\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Soara\Debugger\Debugger;
use Soara\Debugger\Impersonation\Impersonator;
use Throwable;

class ImpersonationController
{
    /**
     * Posted from the bar inside the host app, so it is a classic form post
     * that redirects, not an API call.
     */
    public function stop(Request $request, Impersonator $impersonator): RedirectResponse
    {
        try {
            $signedIn = $impersonator->stop($request);
        } catch (Throwable) {
            return redirect()->to('/');
        }

        if ($signedIn && Debugger::check($request) && Debugger::moduleEnabled('users')) {
            return redirect()->route('debugger.index', ['view' => 'users'])
                ->with('debugger.notice', ['tone' => 'success', 'text' => 'You are back on your own account.']);
        }

        return redirect()->to('/');
    }
}
