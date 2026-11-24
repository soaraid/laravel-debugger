# Merilis versi baru

Rilis dikumpulkan: beberapa perbaikan dicek dulu, lalu dirilis sekali.

1. Pastikan `main` hijau di CI dan `dist/` sudah di-build ulang kalau ada perubahan CSS/JS.
2. Di `CHANGELOG.md`, pindahkan isi `## [Unreleased]` ke bagian versi baru
   `## [x.y.z] - YYYY-MM-DD`, dan perbarui link perbandingan di bawah file.
   Tulis untuk pemakai package (Added / Changed / Fixed / Removed), satu kalimat per poin.
3. Naikkan `version` di `docs/labs.json`.
4. Commit langkah 2 dan 3 bersama perubahan terakhir, dalam satu commit. Tanpa trailer
   Co-Authored-By atau nama Claude.
5. Tag dan push:

   ```bash
   git tag -a vx.y.z -m "vx.y.z"
   git push origin main vx.y.z
   ```

6. Buat GitHub Release dengan isi bagian versi itu dari CHANGELOG.
7. Packagist membaca tag lewat webhook. Aplikasi pemakai: `composer update soara/laravel-debugger`,
   lalu commit `composer.lock`.
