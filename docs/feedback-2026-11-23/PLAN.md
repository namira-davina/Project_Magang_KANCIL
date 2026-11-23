# Plan — Feedback Imigrasi 23 Nov 2026 (feedback user 4)

Sumber: `Chat WhatsApp dengan +62 895-4102-44411` (23/11/26 09.36–09.59) + 4 screenshot (IMG-20261123-WA0007..0010).
Branch bot: `fix/feedback-imigrasi-23nov`. Landing (widget chat bot): branch `fix/feedback-imigrasi-23nov` di repo `landing`.

## 1. Audit: relevan & belum selesai?

Dicek terhadap kode `main` (581c967, sama dengan prod) dan data prod (read-only, 23 Nov ~10.00 WIB).

| # | Permintaan Imigrasi | Bukti | Status sebelum fix |
|---|---|---|---|
| F1 | WA0007 (Paspor Anak, centang di jawaban "Siapa yang menandatangani surat pernyataan…"): tambah **surat pernyataan bermaterai** | ANK-005 di `database/data/faqs.php` dan DB prod: "surat pernyataan" tanpa meterai. ANK-001 sudah "bermeterai". | Relevan, belum |
| F2 | WA0008 (menu 1 > 2 Paspor Baru): tambah **bukti permohonan M-Paspor** | PSP-001 prod: "KTP elektronik, Kartu Keluarga, dan salah satu dari akta lahir/ijazah/buku nikah…" | Relevan, belum |
| F3 | WA0009 (contoh PSP-002): jawaban dibuat **daftar "Persyaratan:"** | PSP-001 satu-satunya FAQ persyaratan yang masih paragraf (22 FAQ persyaratan lain sudah bullet). | Relevan, belum |
| F4 | "menu 2 … ada yang bug untuk kembali ke menu utamanya" | Footer jawaban: "Ketik 0 untuk kembali ke menu **sebelumnya**", tetapi `answerAction` mengirim **menu utama**. Menu 2 bertingkat 3 (M-00 > M-08 > M-09 > jawaban). Log prod 23 Nov: dua penguji mengulang `2 > 1 > N` lalu `0` lebih dari 10 kali karena setiap `0` melempar ke menu utama. Menu 1 juga kena (pola `1 > N > 0 > 1`). Direproduksi lokal: `2,1,1,0 → M-00`; `7`/`9` setelah jawaban = MSG-01. | **Bug nyata**, belum |
| F5 | WA0010 (Cek Status): contoh nomor diganti, "kode kita depannya 1568xxxxx" | `BotScript::statusPrompt()` dan widget landing: "Contoh: 1498000000012345". | Relevan, belum |

Tidak ditemukan masalah lain di log prod hari ini: alur cek status (nomor + tempat dalam 1 pesan) jalan, antrean jalan, batal antrean jalan.

## 2. Keputusan pemilik (23 Nov)

- F4: **0 = menu utama di semua layar, 9 = menu sebelumnya** setelah jawaban (konsisten dengan Skenario 3.2: 9 kembali, 0/99 menu utama). Teks footer menyimpang sedikit dari Bogor; berlaku menu 1, menu 2, jawaban AI, dan Pesan 5.
- F1: **hanya ANK-005** (yang dicentang). Ejaan KBBI "bermeterai" (sama dengan ANK-001 dan PSP-015 "Meterai").
- F2: **hanya PSP-001**. PSP-002 (penggantian) tidak diubah → jadi pertanyaan konfirmasi ke Imigrasi.
- Prod: akses DB/env/VPS (`ssh kancil`) diizinkan untuk audit dan deploy.

## 3. Desain

### F4 Navigasi setelah jawaban
- `BotScript::answerFooter()`:
  ```
  ------------------------

  Butuh bantuan lebih lanjut?

  *Ketik 9* untuk kembali ke menu sebelumnya
  *Ketik 0* untuk kembali ke menu utama
  *Ketik 1* untuk menghubungi Petugas

  *Terima kasih.*
  ```
- `BotEngine::answerAction()`: `9` → `showMenu($state['menu'])`. `state['menu']` tidak berubah saat jawaban ditampilkan, jadi otomatis = menu tempat pilihan jawaban (M-01 / M-08 / M-09 / M-10; jawaban AI = menu aktif saat bertanya, umumnya M-00). `0/99/menu` tetap menu utama, `1/88` tetap petugas. Angka lain = MSG-01 (tidak berubah).
- `9` setelah jawaban mereset hitungan salah input (valid input). Tidak membuat log eskalasi.
- Submenu tidak berubah: tetap punya opsi angka "untuk kembali ke menu sebelumnya" (12/4/7/4) dan "Ketik 0 menu utama".
- Widget landing (`engine.ts`) diberi perilaku sama; `bot-script.json` diregenerasi (`pnpm bot:sync`).
- Ditolak: membuat `0` = menu sebelumnya (arti 0 jadi ganda), atau hanya mengganti label (penguji tetap harus mengulang 2 > 1).

### F1–F3 Konten FAQ (sumber tunggal `database/data/faqs.php` → DB lewat `kancil:faq-sync`)
- PSP-001:
  ```
  *Persyaratan:*
  • KTP elektronik
  • Kartu Keluarga
  • Akta lahir / ijazah / buku nikah
  • Bukti permohonan M-Paspor
  Bawa berkas asli dan fotokopi ukuran A4.
  ```
- ANK-005 pertanyaan: "Siapa yang menandatangani surat pernyataan **bermeterai** jika kedua orang tua bercerai hidup?" (pertanyaan tampil tebal di jawaban menu 1 > 4, tepat di baris yang dicentang). Jawaban tidak diubah.
- `FaqInventoryContractTest`: PSP-001 masuk `REPLACED` (kata kunci wajib), ANK-005 masuk daftar deviasi pertanyaan baru (`QUESTION_REPLACED`) dengan komentar sumber "feedback user 4".
- Header komentar `faqs.php` mencatat deviasi baru.

### F5 Contoh nomor permohonan
- Pindah ke `config('kancil.application_number_example')` = `1568000000012345` (16 digit, awalan kode kantor 1568). Dipakai `statusPrompt()` dan diekspor ke landing.
- Validasi TIDAK dipersempit ke awalan 1568 (risiko menolak nomor sah dari tempat lain / salah ketik ringan sudah ditangani petugas). Tetap 10–25 digit.
- Komentar contoh `1498...` di `BotEngine` diganti; seluruh test/e2e memakai 1568.

## 4. Test (wajib hijau sebelum PR)

Unit
- `BotScriptTest`: footer jawaban baru (urutan 9/0/1, tanpa spasi ganda); `statusPrompt` memakai contoh dari config dan berawalan 1568; tidak ada `1498` di teks bot.
- `FaqInventoryContractTest`: deviasi PSP-001/ANK-005 terdokumentasi; kontrak lain tetap.
- `FaqContentFeedback4Test` (baru): PSP-001 format daftar + bukti permohonan M-Paspor; ANK-005 bermeterai; PSP-002 tidak berubah.

Feature
- `AnswerNavigationTest` (baru, data provider SEMUA jalur `BotScript::ANSWERS`): buka jawaban → `9` = menu induk yang benar (kode menu + teks submenu persis); `0` = M-00; `99`/`menu` = M-00; `1`/`88` = antrean dengan menu_path benar; angka lain = MSG-01 lalu footer.
- Regresi alur penguji prod: `2,1,1,9,2,9,3` (tarif berurutan tanpa ke menu utama), `2,2,1,9,2`, `1,2,9,3`, `2,3,9` (Lokasi → M-08).
- `9` setelah Pesan 5 (FAQ ditahan) dan setelah jawaban AI.
- `9` tidak memengaruhi langkah Cek Status (tetap MSG-01) dan antrean (tetap pengingat antrean).
- Salah input 3x setelah jawaban tetap MSG-03 (counter direset oleh 9).
- `ExcelMatrixTest`/`BotScenarioTest`/`PassportStatusCheckTest`/`GlobalCommandsTest`: contoh 1568.
- `MenuAnswerResolverTest`: menu 1 > 2 dan 1 > 4 menampilkan teks baru dari DB.
- `FaqSyncCommandTest`: sinkron PSP-001/ANK-005 ke DB lama mengubah isi tanpa mengubah status terverifikasi.
- Webhook end-to-end: `2,1,1,9` lewat `/webhook/balesotomatis` + echo gateway → teks terkirim ke API berisi submenu tarif.

E2E (Playwright, `pnpm test:e2e`)
- `feedback-imigrasi-4.spec.js`: dashboard Percakapan menampilkan riwayat `2 > 1 > 1 > 9` dengan submenu tarif sesudah jawaban; FAQ PSP-001/ANK-005 di halaman FAQ dashboard menampilkan teks baru; tiket cek status memuat `1568000000012345`.

Landing
- `engine.test.ts`: 9 setelah jawaban = menu induk untuk semua jawaban; 0 = M-00; contoh 1568.
- `content.test.ts` / bot-script dikunci terhadap Laravel (sudah ada) → regenerasi `pnpm bot:sync` + `pnpm faq:sync`.
- `tests/e2e/chatbot.spec.ts`: alur widget `2 > 1 > 1 > 9`.

## 5. Deploy (setelah PR di-merge user)

1. Backup: `mysqldump … | gzip > ~/backup/kancilsapa_db_20261123_HHMM_pre-pr10.sql.gz`.
2. `git pull` (main), `php artisan config:cache`.
3. `php artisan kancil:faq-sync` (dry-run) → harus tepat 2 perubahan isi: PSP-001 (answer), ANK-005 (question); 0 dilewati (cek `updated_by` null). Lalu `--apply`. Status tetap terverifikasi.
4. Smoke test `php artisan kancil:simulate` (atau chat uji): `2,1,1,9` → submenu tarif; `1,2` → daftar baru; `1,4` → bermeterai; `1,11` → contoh 1568.
5. Landing: merge PR → CI auto-deploy.

## 6. Pertanyaan ke Imigrasi (tidak memblokir)
- PSP-002 (penggantian paspor) juga daftar lewat M-Paspor: perlu "Bukti permohonan M-Paspor" juga?
- Apakah semua tempat permohonan (MPP Banyumas/Purbalingga, Ngapak) juga berawalan 1568? Jika ya, validasi awalan bisa ditambahkan.
- Footer jawaban untuk menu WNA perlu versi bahasa Inggris?

## 7. Realisasi (23 Nov)

Bot (`fix/feedback-imigrasi-23nov`): f530892 plan, 6c266d0 fix + test, 325d6ed e2e + checklist (`checklist-uji-ulang.md`).
Landing (`fix/feedback-imigrasi-23nov`): 3ba1f03.

Nama file test akhir: `tests/Feature/AnswerNavigationTest.php`, `tests/Feature/FeedbackImigrasi4ContentTest.php`
(menggabungkan rencana FaqContentFeedback4Test + resolver + faq-sync), tambahan kasus 9 di `ExcelMatrixTest` (webhook + echo gateway)
dan `BotAiTest`, `tests/e2e/feedback-imigrasi-4.spec.js`.

Hasil: PHPUnit 926 lulus (8729 assertion); Pint lulus; Playwright bot 12/12; landing vitest 95/95, svelte-check 0 error,
build + `test:build` lulus, Playwright landing 18/18. Sanity: test navigasi baru dijalankan ke `BotEngine` lama → 46 gagal (bug tertangkap).
Catatan: `pnpm lint` landing sudah gagal di `master` (32 file format lama, bukan dari perubahan ini); file yang diubah lolos Prettier/ESLint.

### Tambahan 23 Nov (permintaan pemilik): footer menu WNA dwibahasa
Jawaban dari menu Izin Tinggal (M-08/M-09/M-10, termasuk Pesan 5, jawaban AI di menu itu, dan ulangan footer saat salah input)
memakai `BotScript::answerFooter(english: true)`: versi Inggris miring di dalam kurung di belakang setiap baris. Menu Paspor WNI tetap
bahasa Indonesia. Test: `AnswerNavigationTest` (bahasa footer untuk setiap jalur jawaban), `BotAiTest`, e2e bot dan widget landing.
Hasil akhir: PHPUnit 950 lulus; Playwright bot 12/12; landing vitest 96/96, build lulus, Playwright 18/18.
