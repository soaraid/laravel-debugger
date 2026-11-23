---
title: Instalasi
description: Pasang package dengan Composer, jalankan migration, lalu buka /debugger.
---

Pasang package dengan Composer, jalankan migration, lalu buka `/debugger`. Cuma sekitar satu menit.

## Kebutuhan

- PHP 8.2 atau lebih baru
- Laravel 12 atau 13 (Laravel 13 butuh PHP 8.3)

## Pasang package

Tambahkan lewat Composer:

```bash
composer require soara/laravel-debugger
```

> [!NOTE]
> Service provider terdaftar otomatis. Anda tidak perlu mendaftarkannya sendiri.

## Jalankan migration

Package ini menyimpan riwayat scheduler dan audit log aksi di dua tabel.

```bash
php artisan migrate
```

Nama tabel dan koneksinya bisa diganti. Lihat [Konfigurasi](./configuration.md#tabel-package).

## Publish config

Opsional. Publish kalau Anda ingin mengganti path, mematikan modul, atau mengganti nama tabel.

```bash
php artisan vendor:publish --tag=debugger-config
```

File-nya ada di `config/debugger.php`.

## Buka debugger

Buka `/debugger` di aplikasi Anda, misalnya `http://localhost/debugger`. Halaman pertama adalah [Overview](./overview.md).

## Beri akses

Tanpa pengaturan apa pun, debugger hanya bisa dibuka di environment `local`. Untuk membukanya di tempat lain, definisikan gate `viewDebugger` di `AppServiceProvider`. Setelah gate ini ada, gate-lah yang menentukan siapa yang boleh masuk, termasuk di local:

```php title="app/Providers/AppServiceProvider.php"
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewDebugger', fn ($user) => in_array($user->email, [
        'dev@aplikasianda.com',
    ]));
}
```

Pilihan lain ada di [Kontrol akses](./access-control.md).

> [!WARNING]
> Di production, debugger berjalan dalam mode baca-saja secara default: aksi seperti Retry, Clear, dan Log in as dimatikan. Lihat [Mode baca-saja](./read-only-mode.md).
