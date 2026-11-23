# Format docs package Soara

Aturan untuk folder `docs/` di setiap repo package Soara. Website labs.soara.id
mengambil folder ini dari tiap repo, jadi bentuknya harus sama di semua package.
Satu package = satu repo.

URL di labs: ID di `/<slug-package>/<slug-halaman>`, EN di
`/en/<slug-package>/<slug-halaman>`.

## Struktur folder

```text
docs/
  labs.json
  images/            (opsional, gambar dipakai kedua bahasa)
  id/<slug>.md
  en/<slug>.md
```

- Slug berbahasa Inggris dan SAMA di kedua bahasa, misalnya `introduction`,
  `installation`, `configuration`.
- Kalau satu halaman belum ada di salah satu bahasa, labs menampilkan bahasa lain
  dengan catatan. Usahakan dua-duanya lengkap.
- `docs/` hanya berisi docs publik. Catatan kerja internal (rencana, tahapan,
  aturan ini) ada di `.dev/`.
- `/docs` dan `/.dev` diberi `export-ignore` di `.gitattributes`, jadi tidak ikut
  terpasang lewat Composer. Labs membaca langsung dari repo.

## docs/labs.json

Metadata package untuk beranda, halaman package dan sidebar labs. Isi nilainya
dari `composer.json` dan kode, jangan mengarang.

| Kunci | Isi |
| --- | --- |
| `slug` | Slug package di URL labs, mis. `laravel-debugger` |
| `name` | Nama tampilan |
| `package` | Nama Composer/npm |
| `repository` | URL repo GitHub |
| `stack` | Daftar teknologi, mis. `["Laravel"]` |
| `category` | Satu kategori, mis. `Debugging` |
| `version` | Versi rilis terakhir, tanpa `v` |
| `license` | Sama dengan `composer.json` |
| `requires` | Kebutuhan minimum, mis. `{ "php": "8.2+", "laravel": "11, 12" }` |
| `install` | Satu perintah pasang |
| `description` | `{ "id": "...", "en": "..." }`, satu kalimat |
| `steps` | Langkah mulai cepat per bahasa: `[{ "label": "...", "code": "..." }]` |
| `modules` | Opsional. Bagian package: `slug`, `name`, `icon` (nama ikon Tabler), `description` per bahasa |
| `sidebar` | Per bahasa: `[{ "group": "...", "pages": ["slug", ...] }]` |

Setiap slug di `sidebar` harus punya file di `docs/id` dan `docs/en`.

Cek JSON valid:

```bash
php -r 'json_decode(file_get_contents("docs/labs.json"), flags: JSON_THROW_ON_ERROR);'
```

## Frontmatter

Setiap file dimulai dengan:

```markdown
---
title: Instalasi
description: Satu kalimat untuk hasil pencarian dan pratinjau link.
---
```

- Jangan menulis H1 di isi. Labs memakai `title` sebagai H1.
- Paragraf pertama setelah frontmatter adalah pengantar halaman (lead).
- Bagian memakai `##` (masuk daftar "Di halaman ini") dan `###` bila perlu.

## Konvensi Markdown

Harus tetap terbaca baik di GitHub.

- Catatan: `> [!NOTE]` untuk tips, `> [!WARNING]` untuk hal yang bisa merusak atau
  soal keamanan. Satu atau dua kalimat.
- Blok kode selalu dengan bahasa, dan judul bila itu file:

  ````markdown
  ```php title="app/Providers/AppServiceProvider.php"
  ```
  ````

  Perintah terminal memakai ` ```bash `.
- Tautan antar halaman relatif: `[Konfigurasi](./configuration.md)`. Labs
  mengubahnya ke URL yang benar.
- Gambar: `![Alt yang menjelaskan isi](../images/logs.png)`. Gambar dipakai
  kedua bahasa, jadi teks di dalam gambar sebaiknya bahasa UI package (Inggris).
- Tabel boleh, tapi pendek. Daftar biasanya lebih mudah dibaca.

## Aturan teks

- ID: bahasa Indonesia natural, sapa pembaca dengan "Anda", kalimat pendek,
  hangat tapi tenang. Tanpa tanda pisah panjang (em dash). Istilah teknis tetap
  bahasa Inggris dan tidak dimiringkan: migration, config, gate, queue, job,
  environment, production, cache, retry.
- EN: plain English, sapa pembaca dengan "you", kalimat pendek.
- Judul halaman huruf kalimat, bukan Title Case.
- Tombol dan aksi ditulis persis seperti di UI.
- Tanpa "terbaik", klaim tanpa bukti, atau emoji.
- Dokumentasikan hanya yang benar-benar ada di kode. Setiap nama config, env var,
  tag publish, command, route dan gate dicek ke kode.
- Nilai contoh harus jelas contoh: `dev@aplikasianda.com`, `dev@yourapp.com`,
  `https://api.example.com`.
- Screenshot dari aplikasi contoh (workbench), jangan dari aplikasi klien.
