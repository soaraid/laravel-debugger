# Tahapan development

Rincian kerja per tahap dari `.dev/PLAN.md` bagian 8. Centang tiap butir saat selesai. Setiap tahap ditutup dengan `composer test` hijau dan pengecekan manual di workbench (`composer serve`, lalu buka `/debugger`).

## Asumsi sementara

Asumsi untuk keputusan yang belum dijawab (PLAN.md bagian 10). Mudah diganti selama tahapnya belum dimulai.

| Keputusan | Asumsi | Mulai berpengaruh di |
|---|---|---|
| Versi minimal | Laravel 11 + 12, PHP 8.2 | Tahap 0 |
| Logs | **Diputuskan:** parser sendiri, tanpa log-viewer | Tahap 1 |
| Bahasa UI | Inggris dulu, semua teks lewat file `lang/` supaya bahasa Indonesia tinggal ditambah | Tahap 0 |
| Distribusi | Diputuskan di tahap 5, tidak menghambat tahap sebelumnya | Tahap 5 |

---

## Tahap 0 · Kerangka

**Tujuan:** package bisa dipasang, `/debugger` tampil dengan header, tab, dan gaya yang sesuai desain.

- [x] `composer.json`: nama `soara/laravel-debugger`, autoload `Soara\Debugger\`, auto-discovery provider, script `test`, `serve`, `lint`
- [x] Dev dependency: `orchestra/testbench`, `pestphp/pest`, `pestphp/pest-plugin-laravel`, `laravel/pint`
- [x] `DebuggerServiceProvider`: memuat config, route, view (namespace `debugger`), migration, dan translation
- [x] `config/debugger.php` lengkap dengan default (PLAN.md bagian 4), bisa di-publish
- [x] `Debugger::auth()` dan gate `viewDebugger`. Default: hanya environment local
- [x] Middleware `Authorize` dan mode read-only (otomatis aktif di production)
- [x] Tanpa auth yang valid: 403. Kalau `enabled=false`: 404
- [x] Workbench: model User, seeder user contoh (data modul lain diisi bertahap)
- [x] Halaman login sederhana untuk dev: `/workbench/login` (masuk sebagai Dev Admin) dan `/workbench/impersonate-demo`
- [x] Frontend: `package.json`, Alpine, Vite build ke `dist/` dengan nama file berversi, font Geist ikut di-bundle (tanpa Google Fonts). **Tanpa Tailwind**: CSS komponen diambil langsung dari CSS komponen Doop (`resources/css/components.css`), jadi nilainya sama persis dengan desain
- [x] `resources/css/tokens.css` dari ekspor token Doop
- [x] `AssetController`: menyajikan `dist/` dengan header cache panjang
- [x] Layout (`<x-debugger::layout>`) + komponen: `header`, `tab`, `page-title`, `button` (semua variant, disabled, loading), `notice`, `empty-state`, `box`
- [x] Halaman placeholder untuk 8 modul. Modul yang dimatikan di config tidak muncul di tab
- [x] GitHub Actions: test matrix PHP 8.2–8.4 × Laravel 11–12, Pint
- [x] `.gitattributes` export-ignore untuk `workbench/`, `tests/`, `docs/`, `resources/css`, `resources/js`

**Test:** akses ditolak tanpa gate; read-only aktif di production; tab mengikuti config modul.
**Cek manual:** header dan tab sama dengan frame AppHeader di Doop.

## Tahap 1 · Logs + Queue

**Tujuan:** bisa membaca log dan mengurus failed job.

Logs
- [x] `LogReader` sendiri: parse format Laravel (`[tanggal] env.LEVEL: pesan` + stack trace multi-baris), baca dari akhir file per potongan supaya file besar (70 MB+) tetap cepat, filter level, pencarian
- [x] Hanya `storage/logs` (path dari config). File di luar itu ditolak
- [x] Komponen `table`, `row` (bisa dibuka), `level`, `code-block`, `search`, `select`, `pagination`
- [x] Halaman Logs sesuai frame Doop: daftar file di kiri, baris dibuka untuk stack trace, frame vendor dilipat
- [x] Keadaan kosong: belum ada file log; tidak ada hasil untuk pencarian (dengan Clear filters)
- [x] Filter tersimpan di query string halaman Logs saja
- [x] Diukur di laravel.log aplikasi klien (71 MB): halaman pertama 38 ms, pencarian ke seluruh file 0,6 s, memori ~8 MB

Queue
- [x] `FailedJobs` lewat `queue.failer`: list, find, retry, retry all, forget
- [x] Hitungan job menunggu dari `queue.connections.{koneksi}.table` (driver database), atau "–" untuk driver lain
- [x] Komponen `confirm-dialog` (Alpine, `<dialog>` + fokus kembali ke tombol pemicu)
- [x] Halaman Queue: baris dibuka untuk exception lengkap; Delete dan Retry all lewat konfirmasi
- [x] Aksi menulis ke audit log; ditolak saat read-only
- [x] Seeder workbench: file log contoh berbagai level, 3 failed job

**Test:** path traversal di parameter file log ditolak; retry memindahkan job; delete butuh request yang benar; read-only menolak aksi.

## Tahap 2 · Scheduler + Cache

**Tujuan:** riwayat scheduler yang akurat dan aksi cache.

Scheduler
- [x] Migration `schedule_runs` (nama tabel dari config)
- [x] `RunRecorder`, listener `ScheduledTaskStarting`, `Finished`, `Failed`, `Skipped`:
  - [x] exit code ≠ 0 → satu baris `failed` (bukan sukses lalu gagal)
  - [x] skip karena overlap → `skipped` dengan alasan (overlap di Laravel lewat filter skip, jadi dibedakan dengan mengecek mutex)
  - [x] `runInBackground` → dicatat saat proses selesai (lewat callback `after`), bukan saat di-spawn
  - [x] teks error disamarkan dengan `Redactor`
- [x] `Heartbeat`: waktu `schedule:run` terakhir di cache; "late" kalau melewati `late_after_minutes`
- [x] Daftar task untuk request HTTP (snapshot jadwal yang disimpan saat `schedule:run`)
- [x] `debugger:prune` (retention hari) dan otomatis didaftarkan di schedule
- [x] Halaman Scheduler: tabel task + riwayat run

Cache
- [x] `CacheActions`: cache, config, route, view, optimize:clear, optimize
- [x] Status cache saat ini
- [x] Rebuild lewat konfirmasi, dengan peringatan soal `.env`

**Test:** skenario exit code 1, overlap, background, dan exception masing-masing menghasilkan satu baris dengan status benar; prune menghapus yang lewat batas; heartbeat late.

## Tahap 3 · Mail + Storage + Connections

Mail
- [x] `MailTester`: kirim lewat queue atau sync; `override_recipient` opsional
- [x] Info mailer (tanpa password), kapan worker terakhir memproses job
- [x] Komponen `field` (dengan error), `radio`
- [x] Pesan hasil: queued (dengan petunjuk ke Queue), terkirim, atau error dengan cara memperbaiki

Storage
- [x] `StorageTester`: upload ke disk privat `storage.disk`/`folder`, list, delete di disk yang benar
- [x] Open: MIME dari daftar ekstensi yang diizinkan, `CSP: sandbox`, `nosniff`; selain itu diunduh
- [x] Cek disk: tulis, baca, hapus dengan waktu respons; sisa ruang

Connections
- [x] `Pinger`: method, URL, header, timeout dari config; hasil berupa kode HTTP, waktu, dan error
- [x] Ping satu dan Ping all; hasil singkat + `row-detail` untuk yang gagal
- [x] Keadaan kosong dengan contoh config

**Test:** file `.txt` berisi HTML tidak pernah disajikan sebagai `text/html`; upload tidak bisa keluar dari folder; ping timeout dan 401 terbaca benar (HTTP di-fake).

## Tahap 4 · Users (impersonate) + audit

- [x] Resolver user: model dari `auth.providers` (atau `users.model`), kolom dari `users.columns`, role dan query opsional
- [x] Halaman Users: pencarian, daftar, Log in as / That's you / Protected
- [x] `Impersonator`: start dan stop, session key, regenerate session, kembali ke akun asal; kalau akun asal sudah dihapus, logout
- [x] Pengaman: bukan diri sendiri, tidak bertingkat, gate `impersonate` dan `beImpersonated`, hanya guard session
- [x] Rute stop di luar gate debugger
- [x] `InjectImpersonationBar`: sisipkan bar ke respons HTML selama impersonate (bisa dimatikan); data impersonator tersedia untuk aplikasi yang pakai banner sendiri
- [x] Audit log: aksi dan sesi impersonate; tabel "Recent sessions"

**Test:** semua pengaman; stop mengembalikan ke akun asal; bar hanya muncul saat impersonate; tabel user custom (nama lain) dan primary key UUID jalan.

## Tahap 5 · Rilis

- [x] README: instalasi, config, gate, keamanan di production
- [x] Ekspor terakhir token Doop dan cocokkan; isi field `code` di komponen Doop
- [ ] Coba pasang di aplikasi klien lewat path repository (branch terpisah); cek di data nyata
- [ ] Putuskan distribusi; tag `v0.1.0`
- [ ] Ganti aplikasi klien ke package; hapus alat debug lama dan log-viewer

## Urutan dan ketergantungan

```
0 Kerangka ──► 1 Logs + Queue ──► 2 Scheduler + Cache ──► 3 Mail + Storage + Connections ──► 4 Users ──► 5 Rilis
               (butuh komponen     (pakai table/row        (pakai field, radio,
                table, dialog)      dari tahap 1)           row-detail)
```

Tahap 2–4 tidak saling bergantung. Urutannya bisa ditukar kalau ada modul yang lebih mendesak.

## Tahap 6 · SPA

- [x] UI pindah ke SPA Vue 3 + Vue Router yang sudah di-build di dalam package (pola Horizon/Telescope). Aplikasi yang memasang tidak butuh Inertia, Livewire atau Vue
- [x] API JSON di `/debugger/api/*` dengan session, CSRF, gate dan read-only yang sama; halaman lain memuat satu shell Blade
- [x] Filter, pencarian dan halaman tersimpan di URL (bisa dibagikan, tombol back jalan)
- [x] Satu bundle (~46 KB gzip) supaya pindah tab tidak menunggu request tambahan; font Latin di-preload
- [x] Cek disk di Storage pindah ke tombol (POST), jadi membuka halaman tidak menulis ke disk
- [x] Test halaman diganti test API; dicek di browser: pindah tab tanpa reload, tanpa error JS
