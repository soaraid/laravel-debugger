---
title: Keamanan
description: Apa saja yang dilindungi debugger saat dipakai di production.
---

Debugger bisa membaca log, menjalankan perintah cache, dan masuk sebagai user lain. Halaman ini menjelaskan apa saja yang menjaganya.

## Akses

- Setiap halaman dan setiap panggilan API melewati pemeriksaan akses. Lihat [Kontrol akses](./access-control.md).
- Tanpa `Debugger::auth()` atau gate `viewDebugger`, debugger hanya terbuka di environment `local`.
- Aksi yang mengubah sesuatu memakai CSRF token dari session aplikasi.
- `DEBUGGER_ENABLED=false` menghapus semua route debugger.

> [!WARNING]
> `DEBUGGER_ENABLED` bawaannya `true`. Di production, debugger tetap tertutup selama Anda tidak mendefinisikan gate yang terlalu longgar. Periksa gate `viewDebugger` Anda sebelum deploy.

## Mode baca-saja

Di production, semua aksi mati secara default. Lihat [Mode baca-saja](./read-only-mode.md).

## Log

- Hanya file di `storage/logs` yang dibaca. File dipilih dari daftar, tidak dari path yang dikirim browser, jadi `../` tidak bisa dipakai.
- Debugger tidak bisa menghapus atau mengubah file log.

## Storage

- File tes disimpan dengan visibility private.
- Hanya file di folder tes yang bisa dibuka atau dihapus.
- File dibuka dengan jenis yang ditentukan dari ekstensi, tidak dari isinya, dan dengan header `Content-Security-Policy: sandbox` dan `X-Content-Type-Options: nosniff`. File yang dibuka tidak bisa menjalankan script di domain aplikasi Anda.

## Password dan token

- Password, key, dan token mailer tidak pernah ditampilkan. Halaman Mail hanya menampilkan mailer, host, port, dan alamat From.
- Header API di Connections tidak pernah ditampilkan.
- Pesan error dari mailer, disk, dan API disaring dulu. Nilai seperti `password=...`, `token=...`, `Bearer ...`, dan URL koneksi seperti `mysql://...` atau `redis://...` diganti `***`.

## Impersonate

- Anda tidak bisa masuk sebagai diri sendiri atau impersonate bertingkat.
- Gate `impersonate` membatasi siapa yang boleh, dan `beImpersonated` membatasi siapa yang boleh dimasuki.
- Session dibuat ulang saat mulai dan saat kembali.
- Bar di aplikasi selalu mengingatkan bahwa Anda sedang memakai akun orang lain.

## Audit log

Setiap aksi tercatat di tabel `debugger_audit_logs`: siapa, aksi apa, targetnya, IP, dan waktunya. Yang dicatat:

- Retry, Retry all, dan Delete di Queue
- Clear dan Rebuild di Cache
- Email tes
- Upload dan Delete di Storage
- Mulai dan berhenti impersonate

Audit log disimpan `audit.retention_days` hari (bawaannya 90) dan dibersihkan oleh `debugger:prune`.
