# Plan Implementasi: Perbaikan & Kalibrasi Hasil Audit 20 November 2026

Branch: `fix/audit-kalibrasi-20nov` (dari `main` @ `4bfccb9`)
Baseline test: 607 lolos (3268 assertion).

## 0. Sumber audit

| Dokumen | Isi | Status terhadap kode |
|---|---|---|
| `Testing_Kancil Sapa.xlsx` (Sheet1 dan Sheet2, 20 Nov) | Waktu respons tiap jalur menu Bogor. Sheet1: jalur 1→7 dan 1→8 lalu `0`/`1` **tidak dibalas**. Sheet2: semua lolos. Respons 5–30 detik. | Bug echo sudah hilang setelah deploy 13:18 (lihat F1). Latensi berasal dari gateway (F2). |
| `3.1 Dokumen Inventarisasi FAQ (30 Okt)` | 61 FAQ, semuanya "Perlu verifikasi" (0 Aktif). | Isi `database/data/faqs.php` menyimpang di beberapa FAQ. Di prod, 61 FAQ berstatus `terverifikasi`+aktif hasil seeder massal (F5, F6). |
| `3.2 Dokumen Skenario FAQ (2–3 Nov)` | Pesan standar 1–9, aturan gate status FAQ, alur pengaduan, cek status ADU, Pesan 7 di luar jam. | Struktur menu 3.2 **tidak** dipakai (keputusan: tetap menu Bogor). Aturan & teks 3.2 yang tidak bertabrakan diterapkan (F3, F4, F7, F8). |
| `4 kategori pengaduan.pdf` (20 Nov) | Bukti alur antrean → ambil → jadikan pengaduan → Diproses → Selesai untuk 4 kategori. | Alur sudah jalan. Temuan: nomor ADU tidak dikirim ke pemohon padahal dialog bilang terkirim (F3). Rata-rata tunggu menggelembung (F9). |

### Keputusan user (20 Nov)
1. Menu tetap gaya Bogor. Isi jawaban dikalibrasi ke Inventarisasi 3.1. Aturan 3.2 yang tidak bertabrakan dengan menu Bogor ikut diterapkan.
2. Gate per status FAQ: jawaban hanya tampil bila FAQ sumbernya Aktif (terverifikasi + aktif). Selain itu bot mengirim Pesan 5.
3. Audit DB prod read-only diizinkan (sudah dilakukan, hasil di F1/F2/F6).

## 1. Temuan

Prioritas: **P0** = bug yang dirasakan pemohon, **P1** = kalibrasi wajib sesuai dokumen, **P2** = mutu dashboard/observabilitas, **P3** = non-teknis/operasional.

### F1 [P0] Bot diam setelah jawaban 1→7 / 1→8 (Excel Sheet1)
- **Bukti prod:** pesan 459, 481, 492, 535, 543 (`meta.source = outgoing_chat`) adalah echo jawaban "Paspor hilang/rusak" yang tercatat sebagai `petugas`. Akibatnya `ProcessOutgoingChat` mengubah mode ke `HUMAN` dan bot berhenti (`handoff_minutes`).
- **Akar masalah:** teks bot saat itu berisi `5 Tahun  :` (spasi ganda) dan garis `----`. Gateway memadatkan spasi dan membuang garis, sehingga pencocokan persis gagal.
- **Status:** sudah diperbaiki oleh PR #7 (normalisasi echo + teks tanpa spasi ganda), dideploy 20 Nov 13:18:41. Setelah itu tidak ada lagi echo yang salah tercatat (0 baris `outgoing_chat`), dan Sheet2 lolos.
- **Yang masih kurang:** belum ada regression test dengan payload echo asli dari prod, dan kejadian ambil alih dari HP tidak tercatat di log. Bila nanti gateway mengubah teks dengan cara lain (emoji, tanda kutip, pemotongan), bug yang sama bisa muncul diam-diam.

### F2 [P3] Respons bot 7–30 detik
- **Bukti prod:** dari webhook masuk sampai balasan dibuat ≤1 detik. Jeda kirim gateway (balasan dibuat sampai echo `outgoing_chat`): n=215, p50 8 detik, p90 11 detik, maks 38 detik. Pesan pertama ("Halo") ditambah 5–6 detik di sisi masuk (dekripsi gateway).
- **Kesimpulan:** latensi berasal dari antrean kirim BalesOtomatis, bukan dari Laravel. Perbaikannya ada di pengaturan device/jeda kirim BalesOtomatis, ditambah metrik supaya bisa dipantau.

### F3 [P0] Nomor pengaduan (ADU) tidak sampai ke pemohon
- `AntreanController::toComplaint` menjanjikan "pemohon menerima nomor pengaduan", dan alert dashboard berbunyi "Nomor pengaduan sudah dikirim ke pemohon". Namun `BotScript::ticketToComplaint()` tidak memuat nomor ADU (terlihat di screenshot PDF 4 kategori).
- 3.2 langkah 8–10 dan skenario 56–57: pemohon menerima nomor `ADU-YYYYMM-NNNN` dan bisa mengecek status (hanya nomor WA pelapor). Di menu Bogor, alur cek status (PGD-09) tidak bisa dijangkau.

### F4 [P1] Pesan standar belum sesuai 3.2
- MSG-01..03 (input salah ke-1/2/3), MSG-04 (media), MSG-05 (FAQ belum Aktif): teks berbeda dari 3.2.
- **MSG-07 (di luar jam layanan) tidak pernah dipakai.** Pemohon yang minta petugas di luar jam tidak diberi tahu (skenario 49). Jam layanan yang di 3.2 tertulis "[diisi]" sudah diketahui: Senin–Kamis 08.00–16.00, Jumat 08.00–16.30 (dari `BusinessHours`).

### F5 [P1] Isi jawaban menu Bogor tidak sesuai Inventarisasi 3.1
| Jalur Bogor | Sumber 3.1 | Selisih |
|---|---|---|
| 1→2 Paspor Baru | PSP-001 | Bogor menambah "Materai Rp 10.000" dan tidak menyebut "berkas asli dan fotokopi A4". |
| 1→3 Penggantian | PSP-002 | 3.1: paspor terbitan 2009 ke atas / luar negeri cukup e-KTP + paspor lama. Bogor mensyaratkan KK, akta, dan materai. |
| 1→4 Paspor Anak | ANK-001..009 | Bogor: "wajib M-Paspor" untuk semua anak. **Bertentangan** dengan ANK-002 (anak di bawah 5 tahun tanpa M-Paspor). Masa berlaku 5 tahun (ANK-004) dan kasus meninggal/yatim/adopsi tidak ada. |
| 1→6 Layanan Prioritas | LYK-002 (KANCIL NARI) | Bogor menambah "Ibu Menyusui" dan tidak menyebut antrean kode C atau tanpa M-Paspor. |
| 1→1 Biaya | TRF-001..004, TRF-011 | Angka sudah konsisten; dasar hukum dan percepatan perlu diselaraskan. |
| 1→7 Hilang | PSP-011 (+TRF-003) | Konsisten. Tambahan SIPANTAS berasal dari feedback user 2 (lebih baru dari 3.1), tetap dipertahankan. |
| 1→5 M-Paspor, 1→8 Rusak, 1→9 Penambahan Nama | **tidak ada di 3.1** | Perlu FAQ baru (PSP-013..015) berstatus *perlu verifikasi* agar bisa diverifikasi petugas. |

Selisih di `database/data/faqs.php` terhadap 3.1:
- PSP-002 diganti teks lain (lihat di atas).
- AMB-004: tanda `;` berubah jadi bullet di tengah kalimat ("…tidak tercantum • silakan hubungi petugas").
- AMB-005: bullet hilang, semua jadi satu paragraf.
- PSP-011 dan TRF-003 berisi tambahan SIPANTAS (sah, dari feedback user 2; dicatat sebagai *deviasi terdokumentasi*).

### F6 [P1] Status FAQ di prod tidak sesuai 3.1, dan AI memakai FAQ belum terverifikasi
- Prod: 61/61 FAQ `terverifikasi` + aktif, hasil seeder 14 Nov, bukan verifikasi petugas. 3.1 menyatakan 0 Aktif.
- `LLM_FAQ_SCOPE=all` di prod: AI memakai semua FAQ selain draft. Ini bertentangan dengan gate status.
- Jawaban menu Bogor hardcode di `BotScript::ANSWERS`, tidak terhubung ke tabel FAQ, sehingga gate status dan suntingan dashboard tidak berpengaruh.

### F7 [P1] Perintah global 3.2
- `88` (petugas) dan `99`/`menu` (menu utama) dari mana saja: sebagian besar sudah jalan. Perlu dipastikan lewat test di semua state (menu, jawaban, cek status paspor, antrean, PGD).
- `9` = kembali **tidak** diterapkan global, karena di menu Bogor angka 9 adalah "Penambahan Nama". Ini deviasi terdokumentasi.

### F8 [P1] Pesan 9 / sesi
- MSG-09 sudah ada. Perlu dicek bahwa teksnya merujuk menu 3 (Bogor), bukan 2 (3.2). Saat ini sudah benar; cukup dikunci dengan test.

### F9 [P2] "Rata-rata tunggu hari ini" menggelembung (287 → 148 mnt di screenshot)
- `TicketStats::today()` menghitung tiket yang **diambil** hari ini, termasuk tiket yang masuk antrean berhari-hari sebelumnya (malam/akhir pekan). Padahal setiap tiket hari itu menunggu 0–2 menit.
- Perbaikan: hitung hanya tiket yang **masuk** hari ini, dalam menit jam layanan (`BusinessHours`), dan tampilkan keterangan.

### F10 [P2] Sisa prototipe di JS dashboard
- `public/js/faq.js`: teks "dari 42 entri" hardcode, dan tombol halaman palsu "7" yang memunculkan `alert('Menampilkan halaman 7')`.
- `public/js/pengaduan.js`: label "Menampilkan X dari Y" memakai jumlah item di halaman aktif. Screenshot memperlihatkan "Menampilkan 1 dari 7 tiket" padahal 7 baris tampil. Diverifikasi lewat E2E, lalu diseragamkan menjadi format "Menampilkan a–b dari N".

### F11 [P2] Observabilitas
- Ambil alih dari HP (`ProcessOutgoingChat`) tidak meninggalkan jejak selain mode. Perlu `Log::warning` + `EscalationLog` (kode baru `H0`, "Ambil alih dari perangkat kantor") berisi cuplikan teks, supaya kasus F1 berikutnya langsung terlihat di dashboard dan log.

## 2. Desain solusi

### 2.1 Jawaban menu dari tabel FAQ (sumber tunggal) + gate status
- `BotScript::ANSWERS[path]` diganti dari teks hardcode menjadi daftar kode FAQ sumber:
  ```php
  '0>4' => ['faq' => ['ANK-001', 'ANK-002', 'ANK-003', 'ANK-004', 'ANK-005', 'ANK-006', 'ANK-007', 'ANK-008', 'ANK-009'], 'code' => 'C2'],
  ```
- Kelas baru `App\Services\Bot\MenuAnswerResolver` memuat FAQ berdasarkan kode, lalu:
  - **semua** FAQ sumber `published` → teks digabung dalam format WhatsApp (judul tebal per FAQ bila lebih dari satu), dibungkus header/footer Bogor;
  - ada yang belum `published` / tidak ditemukan → **Pesan 5** + footer Bogor (0 = menu, 1 = petugas). Event tercatat (`codes: ['MSG-05', 'ANS-…']`) agar laporan bisa menghitung jawaban yang tertahan.
  - Hasil di-cache per request; tidak ada cache lintas request supaya suntingan dashboard langsung berlaku.
- `1>3 Lokasi Kami` tetap dari `config('kancil.office')` (bukan FAQ, tidak di-gate).
- FAQ baru di luar 3.1: `PSP-013` (pendaftaran M-Paspor), `PSP-014` (paspor rusak), `PSP-015` (penambahan nama). Teks diambil dari jawaban Bogor saat ini; `source = "Feedback user 1/2 (di luar Inventarisasi 3.1)"`; status default `perlu_verifikasi`.
- Pemetaan lengkap (final):
  | Jalur | FAQ sumber |
  |---|---|
  | 0>1 Biaya Paspor | TRF-001, TRF-002, TRF-004, TRF-003, TRF-011 |
  | 0>2 Paspor Baru | PSP-001 |
  | 0>3 Penggantian | PSP-002 |
  | 0>4 Paspor Anak | ANK-001..ANK-009 |
  | 0>5 M-Paspor | PSP-013 |
  | 0>6 Layanan Prioritas | LYK-002 |
  | 0>7 Paspor Hilang | PSP-011 |
  | 0>8 Paspor Rusak | PSP-014 |
  | 0>9 Penambahan Nama | PSP-015 |

### 2.2 Data FAQ dikalibrasi ke 3.1
- `database/data/faqs.php` disamakan dengan 3.1 kata per kata, kecuali deviasi terdokumentasi (SIPANTAS di PSP-011/TRF-003). Format WhatsApp (bullet `•`, tebal `*`) tetap boleh. Isi, angka, dan urutan wajib sama.
- Tiap FAQ di file data diberi `status` sesuai 3.1 (`perlu_verifikasi`).
- Command baru `php artisan kancil:faq-sync`:
  - default **dry-run**: menampilkan diff isi/status prod vs file data, per kode;
  - `--apply`: upsert isi FAQ yang berbeda. FAQ yang disunting manual di dashboard (`updated_by` tidak null) dilewati kecuali ada `--force`;
  - `--reset-status`: menyamakan status dengan file data (mis. 61 → perlu_verifikasi). **Hanya dijalankan atas persetujuan user**, karena efeknya semua menu menampilkan Pesan 5 sampai admin memverifikasi.
  - Semua perubahan tercatat (`Log::info` + ringkasan di output).
- Default `config('llm.faq_scope')` diubah ke `published`. `.env` prod `LLM_FAQ_SCOPE=all` dihapus/diubah saat deploy.

### 2.3 Nomor ADU ke pemohon + cek status ADU
- `BotScript::ticketToComplaint(string $adu)`: "Permintaan Anda telah kami catat sebagai *pengaduan resmi* dengan nomor *ADU-…*. Tanggapan Petugas akan dikirim ke chat ini. Untuk cek status, ketik nomor pengaduan tersebut kapan saja."
- `BotEngine`: pesan yang cocok `^ADU-\d{6}-\d{4}$` (tidak peka huruf/spasi), dari state mana pun selain langkah isian PGD, dijawab status (PGD-10) bila nomor milik WA tersebut, atau teks "tidak ditemukan untuk nomor WhatsApp ini" (skenario 57). Ini tidak mengubah menu Bogor.
- Notifikasi perubahan status (Diproses/Selesai) menyertakan nomor ADU.

### 2.4 Pesan standar 3.2
- MSG-01..05 memakai teks 3.2. Rujukan "99 untuk menu utama" disesuaikan menjadi "*0* untuk menu utama" (konvensi Bogor), sementara `99` tetap diterima.
- MSG-07 dipakai di `escalate()` saat `! $hours->isOpen()`: pesan handoff + "Saat ini di luar jam layanan petugas. Jam layanan: Senin–Kamis 08.00–16.00 WIB, Jumat 08.00–16.30 WIB." Teks jam diambil dari `BusinessHours` (bukan hardcode), tanpa janji waktu balasan (catatan 3.2 no. 3).

### 2.5 Pengerasan echo (F1/F11)
- Regression test memakai **payload echo asli prod** (pesan 543 vs 542) dan varian lain: spasi ganda, NBSP, garis hilang, CRLF, emoji dengan/tanpa variation selector, kutip lengkung, trailing whitespace.
- Normalisasi ditambah: hapus variation selector `\x{FE0F}`, samakan kutip lengkung/lurus, dan hapus `*_~` hanya untuk pembandingan.
- Ambil alih dari HP dicatat (`Log::warning` + `EscalationLog` kode `H0`) dan tampil di timeline percakapan.

### 2.6 Statistik & dashboard
- `TicketStats::today()`: rata-rata tunggu = tiket `queued_at` hari ini yang sudah diambil, dalam menit jam layanan. Keterangan "tiket masuk hari ini" ditambahkan di kartu.
- `faq.js`: hapus angka hardcode dan tombol palsu; pagination dihitung dari data.
- `pengaduan.js`: label "Menampilkan a–b dari N tiket".

## 3. Urutan kerja (commit kecil, masing-masing dengan test)

1. `test: regression echo prod payload 0>7/0>8 + varian normalisasi` → lalu `fix(echo): normalisasi tahan emoji/kutip; catat ambil alih H0`
2. `feat(faq): data FAQ sesuai Inventarisasi 3.1 + PSP-013..015 + status` (+ test kontrak 61+3 kode)
3. `feat(bot): jawaban menu dari FAQ sumber + gate status (Pesan 5)`
4. `feat(faq): command kancil:faq-sync (dry-run/apply/reset-status)`
5. `feat(bot): pesan standar 3.2 + Pesan 7 di luar jam`
6. `fix(antrean): nomor ADU dikirim ke pemohon + cek status ADU via chat`
7. `fix(antrean): rata-rata tunggu hari ini berbasis tiket masuk hari ini`
8. `fix(dashboard): pagination faq.js & pengaduan.js tanpa angka hardcode`
9. `chore(config): LLM_FAQ_SCOPE default published`
10. `test(e2e): Playwright dashboard + E2E webhook matriks Excel`
11. `docs: catatan deviasi terdokumentasi, runbook deploy, checklist uji ulang`

## 4. Strategi test

Target: seluruh suite hijau, ditambah test baru per temuan. Tidak ada test yang di-skip.

### 4.1 Unit (`tests/Unit`)
- `MenuAnswerResolverTest`: published semua → gabungan teks; salah satu belum → Pesan 5; kode hilang → Pesan 5; urutan sesuai pemetaan.
- `BotScriptMessagesTest`: teks MSG-01..09 persis 3.2 (dengan adaptasi 0/99), MSG-07 memuat jam dari `BusinessHours` (Jumat 16.30).
- `EchoNormalizerTest`: tabel varian (≥10 kasus) termasuk payload prod.
- `TicketStatsTest`: tiket kemarin yang diambil hari ini tidak dihitung; menit jam layanan.
- `FaqInventoryContractTest`: `faqs.php` memuat tepat 61 kode 3.1 + PSP-013..015, isi sama dengan snapshot 3.1 (`tests/Fixtures/inventaris-3.1.json`, diekstrak dari PDF), deviasi hanya pada daftar yang diizinkan.

### 4.2 Feature (`tests/Feature`)
- `ExcelMatrixTest` (data provider): **setiap sel Excel Sheet1/Sheet2** sebagai skenario: Halo → 1 → {1..11} → {0,1} → {0,1,2} dan menu 2/3. Assert balasan ada (tidak diam), kode pesan benar, mode percakapan benar. Jalur 1→7 dan 1→8 diulang **dengan echo prod disuntikkan** di antara langkah.
- `FaqGateTest`: FAQ belum Aktif → Pesan 5 di menu dan AI tidak memakainya. Aktifkan dari dashboard → jawaban langsung tampil.
- `ComplaintFromTicketTest`: 4 kategori (paspor/WNA/petugas/lainnya) sesuai PDF: prioritas Sedang/Sedang/Tinggi/Rendah, pesan ke pemohon memuat ADU, notifikasi Diproses/Selesai memuat ADU, status bisa dicek lewat chat, nomor milik orang lain ditolak.
- `GlobalCommandsTest`: 0/99/menu/88 dari setiap state (menu, jawaban, cek status paspor, PGD, antrean menunggu, antrean ditangani).
- `OutOfHoursTest`: Sabtu, libur nasional, Jumat 16.15 (buka) vs 16.31 (tutup) → Pesan 7.
- `FaqSyncCommandTest`: dry-run tidak mengubah DB; `--apply` tidak menimpa suntingan manual; `--reset-status` mengubah status.
- Test lama yang mengasumsikan teks hardcode diperbarui, bukan dihapus.

### 4.3 E2E
- **Pipeline WhatsApp (PHPUnit, HTTP nyata ke route webhook):** payload webhook BalesOtomatis → job → `Http::fake` gateway. Assert isi request kirim. Mencakup alur penuh: sapa, menu, jawaban, echo, antrean, ambil petugas, jadikan pengaduan, update status, cek ADU.
- **Dashboard (Playwright, `tests/e2e`)**: server lokal `php artisan serve` + SQLite `database/e2e.sqlite` (`migrate:fresh --seed --env=e2e`). Skenario:
  1. login admin & petugas;
  2. antrean: tiket masuk (disuntik via webhook) → Ambil → Buka Chat → kirim pesan;
  3. Jadikan Pengaduan untuk 4 kategori → alert berisi nomor ADU → muncul di halaman Pengaduan dengan prioritas benar;
  4. Pengaduan: Diproses → Selesai, tanggapan terkirim (cek tabel messages via endpoint test-only atau DB);
  5. pagination Pengaduan & FAQ (label benar, tidak ada alert palsu);
  6. kartu rata-rata tunggu tidak memakai tiket kemarin.
- Script: `pnpm test:e2e`. Dev dependency `@playwright/test` (versi dikunci); browser Chromium diunduh sekali (`pnpm exec playwright install chromium`).
- CI (`.github/workflows/ci.yml`): job `e2e` terpisah setelah job PHP.

### 4.4 Uji manual pasca-deploy
Checklist berbasis Excel (kolom "Harapan" berisi kode pesan/teks), dijalankan petugas di WA asli. Lampiran: `docs/audit-2026-11-20/checklist-uji-ulang.md`.

## 5. Rollout (butuh persetujuan user di tiap langkah prod)
1. Backup DB prod (`mysqldump`).
2. Merge PR → `git pull` di server → `php artisan migrate --force` (bila ada) → `optimize:clear`.
3. `php artisan kancil:faq-sync` (dry-run) → tinjau diff bersama user → `--apply`.
4. Ubah `.env` prod `LLM_FAQ_SCOPE=published` → `config:cache`.
5. Keputusan user: jalankan `--reset-status`? (Ya = semua menu Pesan 5 sampai admin memverifikasi tiap FAQ; Tidak = status prod tetap terverifikasi.)
6. Smoke test WA: Halo → 1 → 7 → 0 → 1 → 2, dan 3 → jadikan pengaduan → cek ADU.

## 6. Non-teknis (P3)
- **BalesOtomatis:** cek pengaturan jeda/delay kirim dan "typing" di member area device. Turunkan bila aman dari risiko blokir. Target p50 < 4 detik. Pastikan "AI Powered" BalesOtomatis tetap **mati** (Opsi B).
- **Verifikasi FAQ oleh petugas:** 3.1 masih 0 Aktif. Jadwalkan sesi verifikasi (64 FAQ termasuk PSP-013..015), dicentang langsung di dashboard. Hasilnya diekspor balik ke dokumen 3.1 versi baru.
- **Dokumen 3.2** perlu revisi: struktur menu Bogor, jam layanan pengganti "[diisi]", teks skenario 52/57 yang belum ditetapkan (diisi dari implementasi ini).
- **Libur 2027** di `config/holidays.php` sudah ada 26 entri; cocokkan dengan SKB 3 Menteri 2027 ketika terbit.

## 7. Deviasi terdokumentasi (sengaja tidak mengikuti 3.2)
| Aturan 3.2 | Implementasi | Alasan |
|---|---|---|
| Menu 0 WNI / 1 WNA / 2 Pengaduan / 3 Hubungi Petugas | Menu Bogor 1/2/3 | Keputusan user 20 Nov (feedback user 1). |
| `9` = kembali di semua menu | Tidak global; `0` = menu utama | Angka 9 adalah opsi "Penambahan Nama" di menu Paspor Bogor. |
| Menu 3 / 88 = tampilkan kontak | 88 / "hubungi Petugas" = antrean petugas dashboard (+ Pesan 7 di luar jam) | Alur antrean yang terbukti di PDF 4 kategori. |
| Pengaduan swalayan menu 2 (PGD) | Pengaduan lewat petugas → "Jadikan Pengaduan" | PDF 4 kategori. Engine PGD tetap ada (dorman), cek status ADU dibuka lewat ketik nomor. |
| PSP-011, TRF-003 sesuai 3.1 | + info SIPANTAS | Feedback user 2 (lebih baru). |

## 8. Realisasi (21 Nov 2026)

Semua langkah 1–11 selesai di branch `fix/audit-kalibrasi-20nov`. Test: **748 PHPUnit lolos (5.373 assertion)**, naik dari 607 sebelumnya; **5 skenario Playwright lolos** (dijalankan 3x, stabil); Pint dan build frontend lolos.

Koreksi plan selama eksekusi:
- **F3 dikoreksi.** Nomor ADU/LYN memang *sengaja* disembunyikan dari pemohon (feedback user 2, commit 528dc29). Bug sebenarnya: alert dan teks modal dashboard menyatakan nomor sudah dikirim. Yang diperbaiki adalah teks dashboard. Pesan ke pemohon tetap tanpa nomor. Cek status ADU lewat chat tetap ditambahkan untuk kasus petugas memberitahukan nomornya.
- **PSP-002** memakai versi feedback user 2 (e-KTP, KK, akta, paspor lama), bukan teks 3.1 ("cukup e-KTP + paspor lama"), karena feedback lebih baru. Dicatat sebagai deviasi di kontrak test. **Perlu dikonfirmasi pihak Imigrasi.**
- **F11:** ambil alih dari HP dicatat lewat `Log::warning` + `meta.takeover` (bukan kode `H0` di escalation_logs), agar laporan eskalasi tidak berubah.
- **MSG-05** tidak menyebut nomor/email kantor, karena 081217000900 adalah nomor bot itu sendiri. Pemohon diarahkan ke "Ketik 1" (petugas).
- **Status 61 FAQ prod dibiarkan** (keputusan user 21 Nov). `--reset-status` tidak dipakai. PSP-013..015 baru berstatus *perlu verifikasi* dan harus diverifikasi admin, kalau tidak menu 1→5/1→8/1→9 menampilkan Pesan 5.
- **Temuan tambahan dari test:** `88` saat bot meminta nomor permohonan (Cek Status Paspor) dianggap salah input. Sudah diperbaiki.
- Playwright dipin ke 1.64.1, karena 1.49 hang di Node 24.

Dokumen pendamping: `RUNBOOK-deploy.md`, `checklist-uji-ulang.md`.
