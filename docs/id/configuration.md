---
title: Konfigurasi
description: Semua pengaturan di config/debugger.php dan env var yang bisa dipakai.
---

Semua pengaturan ada di `config/debugger.php`. Publish dulu kalau Anda ingin mengubahnya:

```bash
php artisan vendor:publish --tag=debugger-config
```

## Env var

| Env var | Kunci config | Bawaan |
| --- | --- | --- |
| `DEBUGGER_ENABLED` | `enabled` | `true` |
| `DEBUGGER_PATH` | `path` | `debugger` |
| `DEBUGGER_DOMAIN` | `domain` | kosong |
| `DEBUGGER_READ_ONLY` | `read_only` | kosong, artinya otomatis |
| `DEBUGGER_MAIL_TO` | `mail.override_recipient` | kosong |
| `DEBUGGER_STORAGE_DISK` | `storage.disk` | `local` |
| `DEBUGGER_DB_CONNECTION` | `database.connection` | koneksi bawaan aplikasi |

## Alamat dan route

- `enabled`: kalau `false`, tidak ada satu pun route debugger yang didaftarkan.
- `path`: alamat debugger. Bawaannya `debugger`, jadi `/debugger`.
- `domain`: isi kalau debugger hanya boleh diakses lewat domain tertentu, misalnya `ops.aplikasianda.com`.
- `middleware`: middleware untuk halaman debugger. Bawaannya `['web']`. Akses dicek setelah middleware ini jalan. Lihat [Kontrol akses](./access-control.md).

```php title="config/debugger.php"
'middleware' => ['web', 'auth'],
```

## Mode baca-saja

`read_only` mematikan semua aksi dan membiarkan halaman tetap bisa dibaca. Kosong (`null`) artinya aktif di production dan mati di environment lain. Lihat [Mode baca-saja](./read-only-mode.md).

## Modul

Setiap modul bisa dimatikan. Modul yang mati hilang dari header, dan API-nya menjawab 404.

```php title="config/debugger.php"
'modules' => [
    'overview' => true,
    'logs' => true,
    'queue' => true,
    'scheduler' => true,
    'cache' => true,
    'mail' => true,
    'storage' => true,
    'connections' => true,
    'users' => false,
],
```

Modul yang tidak tertulis di daftar ini dianggap aktif. Jadi modul baru di versi berikutnya langsung muncul, walaupun config Anda dipublish dari versi lama.

## Pengaturan per modul

### Logs

- `logs.paths`: pola glob, relatif terhadap `storage/logs`. Bawaannya `['*.log', '**/*.log']`. File di luar `storage/logs` tidak pernah dibaca.
- `logs.per_page`: jumlah entri per halaman. Bawaannya `25`.

### Scheduler

- `scheduler.retention_days`: berapa hari riwayat run disimpan. Bawaannya `7`.
- `scheduler.late_after_minutes`: setelah berapa menit tanpa `schedule:run` scheduler dianggap berhenti. Bawaannya `3`.
- `scheduler.prune`: menambahkan `debugger:prune` ke jadwal harian untuk menghapus riwayat lama. Bawaannya `true`.

### Audit log

- `audit.retention_days`: berapa hari audit log aksi disimpan. Bawaannya `90`.

### Mail

- `mail.override_recipient`: semua email tes dikirim ke alamat ini, bukan ke alamat yang diketik di form. Isi lewat `DEBUGGER_MAIL_TO`.

### Storage

- `storage.disk`: disk untuk upload tes. Bawaannya `local`.
- `storage.folder`: folder di disk itu. Bawaannya `debugger`.
- `storage.max_kb`: ukuran maksimal file, dalam KB. Bawaannya `51200` (50 MB). Kalau `upload_max_filesize` di PHP lebih kecil, yang lebih kecil yang dipakai.

### Connections

- `connections`: daftar API eksternal yang bisa di-ping. Lihat [Connections](./connections.md).

### Users dan impersonate

- `users.guard`: guard yang dipakai. Bawaannya `web`. Model user diambil dari provider guard ini, jadi tabel lain atau primary key UUID tetap jalan.
- `users.model`: isi kalau model user berbeda dari provider guard.
- `users.columns`: kolom untuk `name`, `email`, dan `last_active`. `last_active` bawaannya `null`, artinya kolom itu tidak ditampilkan.
- `users.search`: kolom yang dicari saat Anda mengetik di kotak pencarian. Bawaannya `['name', 'email']`.
- `impersonation.enabled`: menyalakan atau mematikan Log in as.
- `impersonation.redirect`: halaman tujuan setelah masuk sebagai user lain. Bawaannya `/`.
- `impersonation.show_bar`: menampilkan bar "You're signed in as …" di aplikasi selama impersonate. Bawaannya `true`.

Peran user dan user mana yang ditampilkan diatur lewat kode, bukan config, supaya config tetap bisa di-cache. Lihat [Users](./users.md).

## Tabel package

```php title="config/debugger.php"
'database' => [
    'connection' => env('DEBUGGER_DB_CONNECTION'),
    'tables' => [
        'schedule_runs' => 'debugger_schedule_runs',
        'audit_logs' => 'debugger_audit_logs',
    ],
],
```

Ganti nama tabel sebelum menjalankan migration. Migration memakai nama dari config ini.

## Publish views

Opsional. Tag `debugger-views` menyalin view Blade package ke `resources/views/vendor/debugger`, supaya Anda bisa mengubahnya:

```bash
php artisan vendor:publish --tag=debugger-views
```

- `app.blade.php`: halaman pembungkus yang memuat debugger.
- `impersonation-bar.blade.php`: bar "You're signed in as …" selama impersonate.
- `mail/test.blade.php`: isi email tes.

Tampilan debugger sendiri sudah di-build dan tidak bisa diubah lewat views ini.

> [!NOTE]
> View yang sudah dipublish tidak ikut berubah saat package diperbarui. Publish hanya kalau memang perlu.
