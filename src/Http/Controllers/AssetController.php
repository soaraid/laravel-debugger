<?php

namespace Soara\Debugger\Http\Controllers;

use Soara\Debugger\Assets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController
{
    private const TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
    ];

    public function __invoke(Assets $assets, string $path): BinaryFileResponse
    {
        $file = $assets->path($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        abort_if($file === null || ! isset(self::TYPES[$extension]), 404);

        $response = new BinaryFileResponse($file, 200, [
            'Content-Type' => self::TYPES[$extension],
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // File names carry a content hash, so they never change in place.
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->headers->addCacheControlDirective('immutable');

        return $response;
    }
}
