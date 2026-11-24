---
title: Logs
description: Baca file log Laravel dengan filter level, pencarian, dan stack trace yang rapi.
---

Halaman Logs membaca file log di `storage/logs` langsung dari disk, tanpa memuat seluruh file ke memory. File ratusan MB tetap bisa dibuka.

![Halaman Logs dengan daftar file di kiri dan entri log di kanan](../images/logs.png)

## Yang ditampilkan

- Daftar file log di kiri, dengan ukurannya. Yang terakhir berubah paling atas; pilih *Oldest first* di atas daftar untuk membalik urutannya, atau ketik sebagian nama di *Find a file*. Log harian (`LOG_STACK=daily`) tampil satu file per hari, misalnya `laravel-2026-11-24.log`.
- Entri log terbaru di atas, dengan jam, level, dan pesan. Daftar entri di-scroll di dalam tabel, setinggi sisa layar, sehingga pagination tetap terlihat. Di layar pendek, daftar tetap menampilkan sekitar tujuh baris dan halaman yang di-scroll.
- Jumlah total entri dan jumlah per level di filter level.

Klik satu entri untuk membukanya. Untuk exception, Anda melihat class, pesan, file dan baris, lalu stack trace. Frame dari `vendor/` disembunyikan dulu dan bisa dibuka dengan tombol "Show … vendor frames". Context JSON ditampilkan dengan format yang rapi.

## Filter dan pencarian

- Filter level: emergency, alert, critical, error, warning, notice, info, debug.
- Pencarian mencari di pesan dan isi entri, tidak membedakan huruf besar dan kecil.

## Config terkait

- `logs.paths`: pola file yang dibaca, relatif terhadap `storage/logs`. Bawaannya `['*.log', '**/*.log']`.
- `logs.per_page`: jumlah entri per halaman. Bawaannya `25`.

## Aksi

Setiap file di daftar kiri punya menu ⋮.

- **File info**: lokasi file, ukurannya, jumlah entri, waktu entri pertama, dan kapan terakhir ditulis.
- **Download**: file aslinya. Tercatat di audit log.

Di bawah garis pemisah ada dua aksi yang mengubah file. Keduanya meminta konfirmasi dulu dan tercatat di audit log.

- **Clear file**: mengosongkan isi file. File-nya tetap ada, dan entri baru ditulis seperti biasa.
- **Delete file**: menghapus file dari `storage/logs`. Tidak bisa dibatalkan.

## Cara kerjanya

Saat file pertama kali dibuka, debugger membuat index kecil berisi posisi dan level tiap entri. Setelah itu hanya bagian yang baru ditambahkan yang dibaca. Index disimpan di `storage/framework/cache/debugger-logs` dan dibuat ulang otomatis kalau file dirotasi atau dikosongkan.

Halaman berikutnya dan sebelumnya diambil lebih dulu di belakang layar, jadi Next dan Previous terasa langsung.

## Mode baca-saja

Clear file dan Delete file dimatikan. Membaca, filter, pencarian, File info, dan Download tetap jalan.

## Batasan

- Hanya format log bawaan Laravel (`[2026-11-23 10:00:00] production.ERROR: ...`) yang dikenali. Baris lain dianggap lanjutan entri sebelumnya.
- Pencarian harus membaca seluruh file. Di file besar, pencarian pertama butuh waktu. Hasilnya diingat 2 menit, jadi pindah halaman pada pencarian yang sama tetap cepat.
