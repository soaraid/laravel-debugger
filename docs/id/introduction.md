---
title: Pengenalan
description: Apa itu Laravel Debugger, untuk siapa, dan apa saja isinya.
---

Laravel Debugger adalah log viewer dan kumpulan halaman ops untuk aplikasi Laravel, dalam satu tempat di `/debugger`. Anda bisa membaca log, mengurus failed job, mengecek scheduler, dan menguji mail atau storage tanpa membuka terminal di server.

## Untuk siapa

Untuk developer yang merawat aplikasi Laravel yang sudah berjalan. Biasanya ini pekerjaan yang dilakukan lewat SSH: membuka `storage/logs`, menjalankan `queue:retry`, atau mengecek apakah cron masih jalan. Di sini semuanya ada di browser, dengan akses yang Anda atur sendiri.

## Isinya

- [Overview](./overview.md): satu daftar yang menunjukkan bagian mana yang perlu dicek.
- [Logs](./logs.md): baca file log dengan filter level, pencarian, dan stack trace yang rapi.
- [Queue](./queue.md): failed job beserta exception-nya, dengan Retry dan Delete.
- [Scheduler](./scheduler.md): task terjadwal, jadwal berikutnya, dan riwayat tiap run.
- [Cache](./cache.md): kosongkan cache aplikasi, config, route, dan view.
- [Mail](./mail.md): kirim email tes lewat queue atau langsung.
- [Storage](./storage.md): upload file tes dan cek apakah disk bisa ditulis.
- [Connections](./connections.md): ping API eksternal yang dipakai aplikasi.
- [Users](./users.md): masuk sebagai user lain untuk melihat aplikasi dari sisi mereka.

Setiap modul bisa dimatikan satu per satu. Lihat [Konfigurasi](./configuration.md).

## Bedanya dengan Laravel Telescope

Laravel Debugger tidak merekam request, query, atau event. Ia membaca apa yang sudah ada di aplikasi Anda: file log, tabel failed jobs, dan config. Yang ia simpan sendiri hanya dua hal kecil: riwayat run scheduler dan audit log aksi di debugger. Jadi tidak ada beban tambahan di setiap request aplikasi. Keduanya bisa dipakai bersamaan.

## Langkah berikutnya

Lanjut ke [Instalasi](./installation.md), lalu atur siapa yang boleh membuka debugger di [Kontrol akses](./access-control.md).
