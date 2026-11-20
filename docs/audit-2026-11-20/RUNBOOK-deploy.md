# Runbook Deploy — fix/audit-kalibrasi-20nov

Server: `ssh kancil` → `/var/www/dashboard.kancilsapa.com`. Setiap langkah yang mengubah prod perlu persetujuan pemilik proyek.

1. **Backup DB**
   `mysqldump kancilsapa_db > ~/backup/kancilsapa_db_$(date +%Y%m%d_%H%M).sql`
2. **Tarik kode** (setelah PR di-merge ke `main`)
   `git pull --ff-only && composer install --no-dev --optimize-autoloader`
   Tidak ada migrasi baru. Tetap jalankan `php artisan migrate --force` (aman, tidak ada perubahan).
3. **Sinkron FAQ (lihat dulu, baru terapkan)**
   `php artisan kancil:faq-sync` → tinjau diff (harapan: `+ PSP-013/014/015/016`, ~ AMB-004/AMB-005/BYR-002/PSP-011/TRF-003 isi, status beda dibiarkan).
   `php artisan kancil:faq-sync --apply` (TANPA `--reset-status`: keputusan 21 Nov, status 61 FAQ dibiarkan).
   FAQ yang pernah disunting di dashboard otomatis dilewati.
4. **Konfigurasi**: di `.env` ubah `LLM_FAQ_SCOPE=all` → `LLM_FAQ_SCOPE=verified` (opsional; status prod semua terverifikasi, jadi efeknya hanya untuk PSP-013..016). Lalu `php artisan optimize:clear && php artisan config:cache`.
5. **Verifikasi PSP-013, PSP-014, PSP-015, PSP-016** di Dashboard → Database FAQ (status Terverifikasi + Aktif). Tanpa ini menu 1→5 (M-Paspor), 1→8 (Rusak), 1→9 (Beda Data), 1→10 (Penambahan Nama) menampilkan Pesan 5 ("Informasi ini sedang diperbarui").
6. **Restart worker antrean**: `php artisan queue:restart`.
7. **Smoke test WA**: Halo → 1 → 7 → 0 → 1 → 8 → 1 → 2 (batal) → 3 → Jadikan Pengaduan → Diproses → Selesai. Lalu jalankan `checklist-uji-ulang.md`.
8. **Pantau** `storage/logs/laravel.log` untuk `Bot diambil alih oleh pesan keluar dari perangkat kantor`. Bila muncul tanpa petugas membalas dari HP, berarti pola echo baru: kirim cuplikan teks log ke developer.

**Rollback**: `git checkout <commit sebelumnya>` + `php artisan optimize:clear`. Data FAQ yang diubah `--apply` dapat dipulihkan dari backup langkah 1 (tabel `faqs`, `faq_keywords`).
