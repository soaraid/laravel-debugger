{{--
    Injected into the host app, which has none of the debugger's CSS, so the
    styles are inline and scoped. Colors are the debugger tokens
    (--color-accent, --color-on-accent).
--}}
@php
    $user = request()->user(config('debugger.users.guard', 'web'));
    $columns = (array) config('debugger.users.columns', []);
    $name = $user ? data_get($user, $columns['name'] ?? 'name', $impersonation['target'] ?? '') : ($impersonation['target'] ?? '');
    $email = $user ? data_get($user, $columns['email'] ?? 'email') : null;
@endphp
<div id="soara-debugger-impersonation" role="status" style="position:sticky;top:0;z-index:2147483647;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:12px 16px;min-height:40px;padding:6px 16px;background:#17754e;color:#ffffff;font:400 13px/1.4 system-ui,-apple-system,'Segoe UI',sans-serif;">
    <span>You're signed in as <b style="font-weight:600">{{ $name }}</b>@if ($email) <span style="opacity:.85">{{ $email }}</span>@endif</span>
    <form method="POST" action="{{ route('debugger.impersonation.stop') }}" style="margin:0">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <button type="submit" style="height:28px;padding:0 12px;border-radius:6px;border:1px solid rgb(255 255 255 / .45);background:transparent;color:#ffffff;font:500 13px/1 system-ui,-apple-system,'Segoe UI',sans-serif;cursor:pointer;">
            {{ $impersonation['name'] ? 'Back to '.$impersonation['name'] : 'Stop impersonating' }}
        </button>
    </form>
</div>
