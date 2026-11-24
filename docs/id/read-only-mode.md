---
title: Mode baca-saja
description: Kapan mode baca-saja aktif, aksi apa yang dimatikan, dan cara mengaturnya.
---

Mode baca-saja mematikan semua aksi yang mengubah sesuatu, tapi semua halaman tetap bisa dibaca. Ini cara aman memakai debugger di production.

## Kapan aktif

Diatur lewat `DEBUGGER_READ_ONLY`:

```dotenv title=".env"
# Kosong (bawaan): aktif di production, mati di environment lain
DEBUGGER_READ_ONLY=

# Selalu aktif
DEBUGGER_READ_ONLY=true

# Mati, walaupun di production
DEBUGGER_READ_ONLY=false
```

Saat aktif, debugger menampilkan "Read-only: actions are turned off in this environment." di atas halaman, dan Overview menulis mode `read-only`.

## Aksi yang dimatikan

- Logs: Clear, Delete
- Queue: Retry, Retry all, Delete
- Cache: Clear, Rebuild
- Mail: Send test email
- Storage: upload, Delete, Check disks
- Connections: Ping, Ping all
- Users: Log in as

Tombolnya tampil tapi tidak bisa ditekan. Kalau aksi dipanggil langsung lewat API, jawabannya 403.

## Yang tetap jalan

- Semua halaman dan daftar.
- Membuka file tes di Storage.
- Kembali dari impersonate, supaya tidak ada yang terjebak di akun orang lain.

## Mematikan sementara di production

Misalnya Anda perlu me-retry failed job di production:

1. Set `DEBUGGER_READ_ONLY=false` di `.env`.
2. Jalankan `php artisan config:clear`, atau `php artisan config:cache` kalau config Anda di-cache.
3. Lakukan aksinya.
4. Kembalikan `DEBUGGER_READ_ONLY` dan ulangi langkah 2.

> [!WARNING]
> Selama mode baca-saja mati, semua yang bisa membuka debugger bisa menjalankan aksi, termasuk Log in as. Nyalakan lagi setelah selesai.
