---
title: Kontrol akses
description: Siapa yang boleh membuka debugger dan siapa yang boleh masuk sebagai user lain.
---

Debugger memeriksa akses di setiap halaman dan setiap panggilan API. Ada tiga cara, dan urutannya penting.

## Urutan pemeriksaan

1. Kalau Anda memanggil `Debugger::auth()`, callback itu yang menentukan. Gate diabaikan.
2. Kalau tidak, dan gate `viewDebugger` ada, gate itu yang menentukan. Ini berlaku di semua environment, termasuk local.
3. Kalau dua-duanya tidak ada, debugger hanya terbuka di environment `local`.

Yang ditolak mendapat 403.

## Gate viewDebugger

Cara yang paling umum. Taruh di `AppServiceProvider`:

```php title="app/Providers/AppServiceProvider.php"
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewDebugger', fn ($user) => in_array($user->email, [
        'dev@aplikasianda.com',
    ]));
}
```

Kalau aplikasi Anda memakai permission, misalnya spatie/laravel-permission:

```php title="app/Providers/AppServiceProvider.php"
Gate::define('viewDebugger', fn ($user) => $user->can('Developer Tools'));
```

> [!NOTE]
> Gate menerima user yang sedang login. Supaya tamu langsung diarahkan ke halaman login, tambahkan `auth` di `middleware` config. Lihat [Konfigurasi](./configuration.md#alamat-dan-route).

## Debugger::auth()

Untuk aturan yang tidak cocok dengan gate, misalnya berdasarkan IP atau header. Callback menerima request:

```php title="app/Providers/AppServiceProvider.php"
use Illuminate\Http\Request;
use Soara\Debugger\Debugger;

public function boot(): void
{
    Debugger::auth(fn (Request $request) => $request->user()?->is_developer);
}
```

## Impersonate

Masuk sebagai user lain di halaman [Users](./users.md) punya dua gate sendiri. Keduanya opsional.

- `impersonate`: siapa yang boleh memakai Log in as. Kalau gate ini tidak ada, semua yang bisa membuka debugger boleh memakainya.
- `beImpersonated`: user mana yang boleh dimasuki. Menerima user yang sedang login dan user tujuan. User yang ditolak tampil dengan tulisan "Protected" dan tanpa tombol.

```php title="app/Providers/AppServiceProvider.php"
Gate::define('impersonate', fn ($user) => $user->can('User.Impersonate'));

// Akun developer tidak boleh dimasuki orang lain.
Gate::define('beImpersonated', fn ($user, $target) => ! $target->hasRole('Developer'));
```

> [!WARNING]
> Tanpa `beImpersonated`, siapa pun yang bisa membuka debugger bisa masuk sebagai user mana pun, termasuk admin. Definisikan gate ini di production.

Selain itu, beberapa batas berlaku selalu: Anda tidak bisa masuk sebagai diri sendiri, tidak bisa impersonate bertingkat, dan setiap mulai dan berhenti tercatat di audit log.
