<?php

namespace Soara\Debugger\Http\Controllers;

use Composer\InstalledVersions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Soara\Debugger\Debugger;

/**
 * Serves the single page that boots the Vue app. Every debugger URL lands
 * here; the app routes on the client and loads data from /api.
 */
class AppController
{
    public function __invoke(Request $request): View
    {
        abort_if(Debugger::modules() === [], 404);

        $base = rtrim((string) parse_url(url((string) config('debugger.path', 'debugger')), PHP_URL_PATH), '/');

        return view('debugger::app', [
            'config' => [
                'base' => $base === '' ? '/' : $base,
                'api' => $base.'/api',
                'app' => (string) config('app.name', 'Laravel'),
                'home' => url('/'),
                'modules' => Debugger::modules(),
                'readOnly' => Debugger::readOnly(),
                'version' => $this->version(),
                'locale' => str_replace('_', '-', app()->getLocale()),
                // A notice set before a full page load, e.g. after switching back from impersonation.
                'notice' => $request->hasSession() ? $request->session()->get('debugger.notice') : null,
            ],
        ]);
    }

    /**
     * "v0.1.0" for a tagged release, "dev" for a branch, a path install or
     * the package's own checkout (Composer reports "1.0.0+no-version-set").
     */
    protected function version(): string
    {
        try {
            $version = (string) InstalledVersions::getPrettyVersion('soara/laravel-debugger');
        } catch (\Throwable) {
            return 'dev';
        }

        return preg_match('/^v?\d+\.\d+\.\d+$/', $version) ? 'v'.ltrim($version, 'v') : 'dev';
    }
}
