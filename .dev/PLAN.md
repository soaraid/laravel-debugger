# soara/laravel-debugger: rencana

> **Update:** UI-nya sekarang SPA Vue yang sudah di-build di dalam package (pola Horizon/Telescope), dengan API JSON di `/debugger/api`. Bagian tentang komponen Blade dan Alpine di bawah ini sudah tidak berlaku: komponennya ada di `resources/js/components` dengan nama dan CSS yang sama.

Package Laravel yang bisa dipasang dengan `composer require soara/laravel-debugger`. Isinya log viewer plus alat kerja untuk debugging. Package ini menggantikan log-viewer dan Developer Tools buatan sendiri di aplikasi klien.

- Desain: canvas Doop https://doop.design/c/htVLr1iE_U (page **Screens** dan **Components**)
- Sumber analisis: aplikasi klien (alat debug lama dan config log-viewer)

## 1. Isi package

| Modul | Isi | Catatan |
|---|---|---|
| Logs | Daftar file log, pencarian, filter level, baris yang bisa dibuka untuk melihat stack trace, pagination | Default hanya membaca `storage/logs` |
| Queue | Failed jobs: retry, delete, retry all, exception lengkap; jumlah job yang menunggu | Lewat `queue.failer`, jadi semua driver failed job didukung |
| Scheduler | Daftar task (cron + bahasa biasa, run berikutnya, run terakhir), riwayat run, peringatan kalau scheduler berhenti | Recorder baru, bug pencatatan dari aplikasi klien diperbaiki |
| Cache | Clear cache app, config, route, view, semuanya; rebuild (optimize); status cache saat ini | Rebuild diberi peringatan |
| Mail | Test email dengan pilihan Queue atau Sync, info mailer, kapan worker terakhir jalan | Password tidak pernah ditampilkan |
| Storage | Upload file uji ke folder privat, daftar file, open dan delete, cek disk (waktu respons, sisa ruang) | Preview aman (lihat bagian 7) |
| Connections | Ping API eksternal yang diatur di config. Opsional: kalau kosong, halaman menampilkan contoh config | Token dari `.env` |
| Users | Impersonate (log in as), daftar sesi terakhir, bar "Back to …" di aplikasi | Pengaman dibawa dari aplikasi klien |

## 2. Struktur folder

```
laravel-debugger/
├── composer.json                 soara/laravel-debugger, PSR-4 Soara\Debugger\ => src/
├── config/debugger.php
├── routes/web.php
├── database/migrations/
│   ├── create_debugger_schedule_runs_table.php
│   └── create_debugger_audit_logs_table.php      aksi + sesi impersonate
├── src/
│   ├── DebuggerServiceProvider.php               config, route, view, migration, gate, listener
│   ├── Debugger.php                              Debugger::auth(fn ($request) => …) seperti Horizon
│   ├── Http/
│   │   ├── Controllers/                          satu controller per modul + AssetController
│   │   ├── Middleware/Authorize.php              gate viewDebugger + mode read-only
│   │   ├── Middleware/InjectImpersonationBar.php
│   │   └── Requests/                             validasi per form
│   ├── Logs/LogReader.php                        parser log sendiri (baca per potongan dari akhir file)
│   ├── Queue/FailedJobs.php
│   ├── Scheduler/RunRecorder.php                 listener event schedule
│   ├── Scheduler/Heartbeat.php
│   ├── Cache/CacheActions.php
│   ├── Mail/MailTester.php, Mail/TestMail.php
│   ├── Storage/StorageTester.php
│   ├── Connections/Pinger.php
│   ├── Impersonation/Impersonator.php
│   ├── Audit/AuditLog.php
│   ├── Support/Redactor.php                      sembunyikan password/token di teks
│   └── Console/InstallCommand.php, Console/PruneCommand.php
├── resources/
│   ├── css/tokens.css                            diekspor dari token Doop (satu sumber nilai)
│   ├── css/app.css                               Tailwind v4 + @theme dari tokens.css
│   ├── js/app.js                                 Alpine + aksi kecil (expand, confirm, ping)
│   ├── views/
│   │   ├── layouts/app.blade.php
│   │   ├── components/                           komponen reusable (bagian 3)
│   │   ├── pages/                                logs, queue, scheduler, cache, mail, storage, connections, users
│   │   └── impersonation-bar.blade.php
│   └── lang/en/, lang/id/
├── dist/                                         hasil build CSS/JS, ikut di-commit
├── tests/                                        Pest + Orchestra Testbench
├── workbench/                                    app Laravel contoh untuk melihat UI saat development
└── docs/
```

## 3. Komponen Blade (sesuai komponen di Doop)

Dipanggil sebagai `<x-debugger::nama>`. Semua warna, ukuran, dan jarak memakai token; tidak ada nilai warna yang ditulis langsung.

| Kelompok | Komponen | Dari Doop |
|---|---|---|
| Dasar | `button` (variant: primary, secondary, ghost, danger, ghost-danger; state: disabled, loading), `field`, `select`, `search`, `radio`, `tab`, `status` (teks berwarna), `level`, `code-block` | Button, Field, RadioOption, Tab, StatusText, LevelTag |
| Kerangka | `header`, `page-title`, `table` + `row` + `row-detail`, `info-list` + `info-item` | AppHeader, PageTitle, LogRow/JobRow/TaskRow/RunRow/ConnRow/UserRow/FileRow/ActionRow, RowDetail, InfoItem |
| Umpan balik | `notice`, `empty-state`, `confirm-dialog`, `pagination` | Notice, EmptyState, ConfirmDialog, Pagination |
| Khusus | `impersonation-bar` | ImpersonationBar |

Delapan jenis baris di Doop jadi satu `table`/`row` generik dengan slot. Setiap halaman tinggal mengisi kolomnya.

Setelah komponen jadi, path file-nya dicatat di komponen Doop (field `code`), supaya desain dan kode saling terhubung.

## 4. Config utama (`config/debugger.php`)

```php
return [
    'enabled'   => env('DEBUGGER_ENABLED', true),
    'path'      => env('DEBUGGER_PATH', 'debugger'),
    'domain'    => null,
    'middleware'=> ['web', 'auth'],
    'read_only' => env('DEBUGGER_READ_ONLY'),        // null = otomatis true di production
    'modules'   => ['logs' => true, 'queue' => true, 'scheduler' => true, 'cache' => true,
                    'mail' => true, 'storage' => true, 'connections' => true, 'users' => true],
    'logs'      => ['paths' => ['*.log', '**/*.log']],   // relatif ke storage/logs
    'scheduler' => ['retention_days' => 7, 'late_after_minutes' => 3],
    'mail'      => ['override_recipient' => env('DEBUGGER_MAIL_TO')],
    'storage'   => ['disk' => env('DEBUGGER_STORAGE_DISK', 'local'), 'folder' => 'debugger', 'max_kb' => 51200],
    'connections' => [ /* 'midtrans' => ['url' => …, 'method' => 'GET', 'headers' => […], 'timeout' => 5] */ ],
    'database' => [
        'connection' => env('DEBUGGER_DB_CONNECTION'),   // null = koneksi default aplikasi
        'tables' => [
            'schedule_runs' => 'debugger_schedule_runs',
            'audit_logs'    => 'debugger_audit_logs',
        ],
    ],
    'users' => [
        'guard'   => 'web',
        'model'   => null,              // null = model dari auth.providers milik guard di atas
        'columns' => [
            'name'        => 'name',
            'email'       => 'email',
            'last_active' => null,      // mis. 'last_login_at'; null = kolom disembunyikan
        ],
        'search'  => ['name', 'email'],
        'role'    => null,              // null = kolom role disembunyikan; atau callable fn ($user) => string
        'query'   => null,              // callable untuk membatasi daftar, mis. fn ($q) => $q->where('is_active', true)
    ],
    'impersonation' => [
        'enabled'  => true,
        'redirect' => '/',
        'show_bar' => true,                           // false kalau aplikasi pakai banner sendiri
    ],
];
```

### Nama tabel dan kolom yang bisa berbeda per project

Prinsipnya: kalau Laravel sudah tahu, nilainya dibaca dari config Laravel. Kalau tidak, disediakan di `config/debugger.php`.

| Yang bisa beda | Diambil dari | Perlu diatur di config debugger? |
|---|---|---|
| Tabel user (nama lain, primary key UUID) | Model user dari `auth.providers` (`$table`, primary key UUID atau int ikut model) | Tidak, kecuali mau model lain (`users.model`) |
| Kolom nama, email, terakhir aktif | `users.columns` | Ya, kalau beda dari `name` / `email` |
| Role | `users.role` (callable) | Opsional; jalan dengan Spatie atau kolom biasa |
| Tabel `failed_jobs` | `queue.failed` lewat `queue.failer` | Tidak |
| Tabel `jobs` (hitungan job menunggu) | `queue.connections.{koneksi}.table` | Tidak |
| Cache store, mailer, disk | `cache.default`, `mail.default`, `filesystems` | Tidak (disk uji upload diatur di `storage.disk`) |
| Tabel milik package | `database.tables` + `database.connection` | Ya, kalau mau nama atau koneksi lain |

Migration package membaca nama tabel dari config, jadi nama yang diubah sebelum `migrate` langsung terpakai.

Gate yang bisa diatur aplikasi:
- `viewDebugger`: siapa yang boleh membuka debugger. Default: hanya di environment local.
- `impersonate` dan `beImpersonated`: siapa yang boleh impersonate, dan siapa yang boleh di-impersonate.

## 5. Rute

| Rute | Fungsi |
|---|---|
| `GET /debugger/{module?}` | Halaman (Blade, tanpa SPA) |
| `POST/DELETE /debugger/…` | Aksi: retry, delete, clear, send, upload, ping, impersonate |
| `GET /debugger/assets/{file}` | CSS/JS dari `dist/`, dengan versi di nama file dan header cache |
| `POST /debugger/impersonate/stop` | Di luar gate `viewDebugger`, karena user yang sedang ditiru biasanya tidak punya akses ke debugger |

## 6. Data yang disimpan package

Nama tabel di bawah adalah default dan bisa diganti lewat `database.tables`.

- `debugger_schedule_runs`: task, ekspresi cron, status (ran, failed, skipped), alasan, waktu mulai dan selesai, durasi, exit code. Dihapus otomatis setelah N hari lewat `debugger:prune`.
- `debugger_audit_logs`: siapa melakukan apa (retry, delete, clear, impersonate start/stop), kapan, dan targetnya.
- Heartbeat scheduler disimpan di cache, tidak perlu tabel.

## 7. Perbaikan yang dibawa dari aplikasi klien dan log-viewer

**Keamanan**
1. Preview file: MIME diambil dari daftar ekstensi yang diizinkan, bukan dari isi file. Ditambah header `Content-Security-Policy: sandbox` dan `nosniff`.
2. Upload uji masuk ke disk privat. Tidak lewat `/storage` publik.
3. Gate hapus dan download log default-nya ditolak.
4. Log viewer default hanya membaca `storage/logs`, bukan log sistem server.
5. Mode read-only otomatis di production, dan semua aksi dicatat di audit log.

**Scheduler**
6. Task yang gagal dengan exit code ≠ 0 tercatat satu kali sebagai failed, bukan dua kali.
7. Task yang di-skip karena overlap tercatat sebagai skipped, bukan sukses.
8. Task `runInBackground` dicatat saat benar-benar selesai.
9. Riwayat disimpan per hari (retention), bukan 100 baris total.
10. Ada heartbeat untuk mendeteksi scheduler yang berhenti.

**Lainnya**
11. Filter dan pencarian tiap halaman terpisah, tidak saling memengaruhi.
12. Hapus file hanya di disk yang benar.
13. Failed job lewat `queue.failer`, jadi semua driver didukung.
14. Asset tidak disisipkan langsung ke HTML seperti log-viewer, jadi bisa di-cache browser dan aman untuk CSP.

## 8. Tahapan

| Tahap | Isi | Selesai kalau |
|---|---|---|
| 0. Kerangka | composer.json, service provider, config, route, workbench, CI; token + build Tailwind; layout, header, dan komponen dasar | Halaman kosong `/debugger` tampil di workbench dengan header dan tab |
| 1. Logs + Queue | LogRepository, halaman Logs; FailedJobs, halaman Queue; confirm dialog, notice, pagination | Bisa baca log dan retry/delete failed job di workbench |
| 2. Scheduler + Cache | RunRecorder, Heartbeat, prune; halaman Scheduler dan Cache | Status ran/failed/skipped tercatat benar, sesuai poin 6–10 |
| 3. Mail + Storage + Connections | MailTester (queue/sync), StorageTester, Pinger | Tiga halaman jalan, termasuk keadaan kosong dan error |
| 4. Users + audit | Impersonator, bar, audit log, sesi terakhir | Bisa log in as dan kembali; pengaman teruji |
| 5. Rilis | README, test lengkap, tag v0.1.0, pasang di aplikasi klien, hapus kode lama aplikasi klien | aplikasi klien memakai package, log-viewer dan alat debug lama dihapus |

Setiap tahap ditutup dengan test (Pest + Testbench) dan pengecekan di workbench.

## 9. Cara mengetes

Package tidak bisa dijalankan sendiri karena butuh aplikasi Laravel. Ada tiga cara mengetesnya, dipakai sesuai kebutuhan:

| Cara | Untuk apa | Perintah |
|---|---|---|
| **Workbench** (Orchestra Testbench) | Melihat dan mencoba UI tanpa project asli. Testbench membuat app Laravel kecil di `workbench/` yang sudah memuat package, lengkap dengan seeder data contoh: file log, failed job, user, riwayat scheduler | `composer serve`, lalu buka `http://localhost:8000/debugger` |
| **Test otomatis** (Pest + Testbench) | Memastikan logika benar: gate, read-only, pencatatan scheduler, keamanan preview, pengaman impersonate | `composer test` |
| **Pasang di aplikasi klien** (path repository) | Uji di project nyata, di branch terpisah | lihat di bawah |

Saat mengubah tampilan, jalankan `npm run dev` di repo package. CSS/JS di-build ulang ke `dist/`, lalu workbench tinggal di-refresh.

Pasang ke aplikasi klien tanpa publish dulu. Di `composer.json` aplikasi klien, tambahkan:

```json
"repositories": [
    { "type": "path", "url": "../../laravel-debugger", "options": { "symlink": true } }
]
```

Lalu jalankan `composer require soara/laravel-debugger:@dev`. Karena memakai symlink, perubahan di repo package langsung terasa di aplikasi klien tanpa install ulang. Path ini relatif terhadap folder aplikasi klien.

## 10. Keputusan yang masih terbuka

1. **Distribusi:** Packagist publik, atau privat (VCS repository / Satis)?
2. ~~Logs~~ **Diputuskan:** parser sendiri, tanpa log-viewer.
3. **Versi minimal:** Laravel 11 + 12, atau hanya 12 ke atas? PHP 8.2 atau 8.3?
4. **Bahasa UI:** Inggris saja, atau Inggris + Indonesia?
