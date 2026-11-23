@php $assets = app(\Soara\Debugger\Assets::class); @endphp
<!doctype html>
<html lang="{{ $config['locale'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $config['app'] }} debugger</title>
    @foreach ($assets->fonts() as $font)
        <link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
    @endforeach
    <link rel="stylesheet" href="{{ $assets->url(\Soara\Debugger\Assets::ENTRY_CSS) }}">
    <script type="module" src="{{ $assets->url(\Soara\Debugger\Assets::ENTRY_JS) }}"></script>
</head>
<body>
    <div id="debugger"></div>
    <script type="application/json" id="debugger-config">@json($config)</script>
    <noscript><p style="padding: 32px">The debugger needs JavaScript.</p></noscript>
</body>
</html>
