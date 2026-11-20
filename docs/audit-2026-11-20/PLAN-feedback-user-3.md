# Plan Implementasi: Feedback Imigrasi 20 Nov 2026 sore (feedback user 3)

Sumber: `Chat WhatsApp dengan +62 895-4102-44411` (teks + 7 gambar, 20 Nov 13.29–16.10).
Branch: lanjut di `fix/audit-kalibrasi-20nov` (belum di-push). Baseline: 748 PHPUnit + 5 Playwright lolos.

## 1. Audit permintaan: masih relevan? sudah terselesaikan?

| # | Permintaan Imigrasi | Bukti | Status di branch sekarang | Relevan |
|---|---|---|---|---|
| R1 | PNBP **dipisah sesuai aturan** di **semua menu**: sebut "paspor 5 thn Rp650.000, dendanya Rp1.000.000", jangan dijumlahkan ("rusak 5 thn 1.650.000") | Chat 13.37–13.38, gambar WA0031 (PNBP PP 45/2024: 650rb, 950rb, denda hilang 1jt, denda rusak 500rb, percepatan 1jt) | **Belum.** Biaya Paspor (1→1) sudah terpisah (TRF-001..004). **Paspor Rusak (PSP-014) masih total** "5 tahun Rp1.150.000". Hilang hanya menyebut denda tanpa tarif dasar. Baru, Penggantian, Anak, Prioritas, Penambahan Nama tidak menyebut PNBP. | Ya |
| R2 | Menu baru **Permohonan Paspor Beda Data** (daftar via SIPANTAS), disisipkan **setelah no. 8** | Chat 13.39–13.40, WA0032 (syarat Beda Data di SIPANTAS) | **Belum** ada. | Ya |
| R3 | "Bukan di penambahan nama, ini beda data": link SIPANTAS salah tempat | Chat 14.40, WA0059 (jawaban Penambahan Nama dengan link SIPANTAS) | **Belum.** PSP-015 dan TRF-003 masih menyebut SIPANTAS untuk penambahan nama. Kategori SIPANTAS hanya Hilang, Rusak, Beda Data (WA0032–34). | Ya |
| R4 | Syarat Hilang/Rusak/Beda Data mengikuti SIPANTAS | WA0032 (Beda Data, 6 dokumen), WA0033 (Rusak, 5), WA0034 (Hilang, 6) | **Belum.** PSP-011 masih teks 3.1 (tanpa "Dokumen Pendukung Lainnya" dan "terdapat tanggal, bulan, tahun lahir"). PSP-014 memuat materai yang tidak ada di SIPANTAS. | Ya |
| R5 | Cek Status Paspor: tambah **tempat permohonan** di bawah nomor permohonan (Imigrasi Cilacap / MPP Banyumas / MPP Purbalingga / Ngapak Banyumas / Ngapak Purbalingga) | Chat 14.42–14.44, WA0060 | **Belum.** Prompt hanya meminta nomor. | Ya |
| R6 | Lokasi: **kantor, MPP Banyumas, MPP Purbalingga** | Chat 14.47, WA0061 | **Belum.** Isinya masih Kantor + MPP Cilacap. | Ya |
| R7 | Menu 2 Izin Tinggal **disesuaikan dokumen inventaris & skenario**, "jangan ikut Imigrasi Bogor" | Chat 14.50 | **Belum.** Menu 2 masih Bogor (Izin Tinggal → antrean, Pengaduan Orang Asing → antrean, Lokasi). FAQ TRF-005..011 dan WNA-001..004 sudah ada di data, tetapi tidak bisa dijangkau dari menu. | Ya |
| R8 | Pairing nomor WA kantor (scan QR / kode verifikasi BalesOtomatis) | Chat 13.30–13.44 | Non-teknis. Belum: menunggu kode dari Mba Tami / Bu Lessa. | Ya (operasional) |
| — | Teks menu utama yang ditempel jam 14.50 masih "Senin–Jumat 08.00–16.00" | Chat 14.50 | **Sudah benar di prod sejak 13.18** (WA0060/0061 pukul 14.06–14.15 menunjukkan "Senin–Kamis … Jumat 08.00–16.30"). Teks itu salinan pesan lama. | Tidak (cukup dijelaskan) |

Kesimpulan: R1–R7 relevan dan belum terselesaikan; R8 operasional.

## 2. Keputusan (disetujui user, 21 Nov)

- Menu Paspor: **9 = Beda Data**, **10 = Penambahan Nama** (tanpa link SIPANTAS), **11 = Cek Status**, **12 = kembali**.
- Lokasi Kami: **Kantor + MPP Banyumas + MPP Purbalingga**; MPP Cilacap dihapus.
- Menu 2: **1 Tarif Izin Tinggal dan Visa**, **2 Pelaporan WNA Meninggal**, **3 Lokasi Kami**, **4 kembali**. Pengaduan orang asing lewat menu utama 3 atau ketik 88.
- Cek Status: **2 langkah** (nomor permohonan → pilih tempat 1–5). Nomor + nama tempat dalam satu pesan juga diterima.
- Sumber tambahan: Google Maps untuk lokasi, `sipantas.kanimcilacap.com` untuk syarat. Situs SIPANTAS **tidak bisa diakses** saat dicek (21 Nov, *connection refused*), jadi syarat diambil dari screenshot SIPANTAS yang dikirim Imigrasi (WA0032–34).

## 3. Desain

### 3.1 Blok PNBP terpisah (R1) — satu sumber, dikunci test
- `config/kancil.php` → `pnbp`: `paspor_5` 650.000, `paspor_10` 950.000, `denda_hilang` 1.000.000, `denda_rusak` 500.000, `percepatan` 1.000.000, `dasar_hukum` "PP Nomor 45 Tahun 2024".
- `BotScript::pnbpBlock(array $items)` merakit, misalnya:
  ```
  *PNBP (PP Nomor 45 Tahun 2024):*
  • Paspor elektronik 5 tahun: Rp650.000
  • Paspor elektronik 10 tahun: Rp950.000
  • Denda paspor rusak: Rp500.000
  ```
  Tidak pernah ada angka gabungan.
- `BotScript::ANSWERS[path]['pnbp']` menentukan baris per menu (tabel 3.3). Gate: blok tampil hanya bila FAQ tarif terkait (TRF-001/002, +TRF-003 untuk denda) terverifikasi. Kalau tidak, seluruh jawaban jadi Pesan 5 (konsisten dengan gate yang sudah ada).
- Contract test: angka di config **harus sama** dengan angka di TRF-001..004 (data FAQ), dan **tidak ada** jawaban menu yang memuat angka gabungan (1.150.000 / 1.450.000 / 1.650.000 / 1.950.000).

### 3.2 Data FAQ (R2, R3, R4)
| Kode | Perubahan | Sumber |
|---|---|---|
| PSP-011 Hilang | Syarat = SIPANTAS: KTP; KK; Surat Kehilangan (Kepolisian); Akte/Ijazah/Surat Nikah (terdapat tanggal, bulan, tahun lahir); Dokumen Pendukung Lainnya (Rekomendasi Biro Umroh/BPIH, Dokumen pelaut, Rekomendasi perusahaan/LPK/Kontrak Kerja, lainnya); Paspor Lama. Pengajuan BAP via SIPANTAS. Tanpa angka PNBP di teks (dari blok). | WA0034 |
| PSP-014 Rusak | Syarat = SIPANTAS (5 dokumen, tanpa materai). Angka gabungan dihapus. | WA0033 |
| **PSP-016 Beda Data (baru)** | "Apa syarat permohonan paspor beda data?" → 6 dokumen SIPANTAS (+ Penetapan Pengadilan), daftar via SIPANTAS. Status perlu verifikasi. | WA0032, chat 13.40 |
| PSP-015 Penambahan Nama | Link SIPANTAS dibuang. Tetap persyaratan + datang ke kantor. | Chat 14.40 |
| TRF-003 | Kalimat SIPANTAS: "hilang, rusak, dan **beda data**" (bukan penambahan nama). | Chat 14.40, WA0032 |

Kontrak 3.1: PSP-011 dan PSP-014 menjadi deviasi terdokumentasi (feedback user 3 + SIPANTAS lebih baru dari 3.1).
Rollout: `kancil:faq-sync --apply` memperbarui isi; PSP-016 dibuat sebagai FAQ baru dan **harus diverifikasi admin** (bersama PSP-013..015).

### 3.3 Menu Paspor (M-01)
| Ketik | Label | FAQ | Blok PNBP |
|---|---|---|---|
| 1 | Biaya Paspor | TRF-001, 002, 004, 003, 011 | (sudah terpisah) |
| 2 | Persyaratan Paspor Baru | PSP-001 | 5 & 10 tahun |
| 3 | Persyaratan Penggantian Paspor | PSP-002 | 5 & 10 tahun |
| 4 | Persyaratan Paspor Anak | ANK-001..009 | 5 tahun (ANK-004: paspor anak 5 tahun) |
| 5 | Pendaftaran Paspor (M-Paspor) | PSP-013 | 5 & 10 tahun |
| 6 | Layanan Prioritas Ramah HAM | LYK-002 | 5 & 10 tahun |
| 7 | Permohonan Paspor Hilang | PSP-011 | 5 & 10 tahun + denda hilang |
| 8 | Permohonan Paspor Rusak | PSP-014 | 5 & 10 tahun + denda rusak |
| **9** | **Permohonan Paspor Beda Data** | **PSP-016** | 5 & 10 tahun |
| 10 | Penambahan Nama pada Paspor | PSP-015 | 5 & 10 tahun |
| 11 | Cek Status Paspor | — | — |
| 12 | untuk kembali ke menu sebelumnya | — | — |

Kunci jalur internal dibuat **stabil** agar data tiket/log lama tetap berlabel benar: `0>9` tetap Penambahan Nama, `0>10` tetap Cek Status, Beda Data = `0>12`. Nomor yang diketik pemohon tidak bergantung pada kunci.

### 3.4 Menu 2 sesuai Skenario 3.2 (R7)
- `M-08` (Pelayanan Izin Tinggal): 1 Tarif Izin Tinggal dan Visa → `M-09`; 2 Pelaporan WNA Meninggal → `M-10`; 3 Lokasi Kami; 4 kembali.
- `M-09` (jalur `1>1`): 1 Izin Masuk Kembali (Re-entry Permit) TRF-005; 2 Izin Tinggal Kunjungan (ITK) TRF-006; 3 Izin Tinggal Terbatas (ITAS) TRF-007; 4 Izin Tinggal Tetap (ITAP) TRF-008; 5 Exit Permit Only TRF-009; 6 Visa Kunjungan Beberapa Kali Perjalanan TRF-010; 7 kembali. Setiap jawaban + TRF-011 (dasar hukum).
- `M-10` (jalur `1>2`): 1 Pelapor dan Batas Waktu (WNA-001, WNA-002); 2 Dokumen Pelaporan (WNA-003); 3 Alur Pelaporan (WNA-004); 4 kembali. Kode eskalasi **C6** bila pemohon minta petugas setelah jawaban (Matriks; sama dengan FAQ_ESCALATION).
- "Kembali" dari M-09/M-10 ke M-08, dari M-08 ke menu utama. 0 = menu utama di semua tempat.
- Label tetap bilingual seperti menu utama (contoh: "Tarif Izin Tinggal dan Visa (Stay Permit & Visa Fees)"), karena pemakainya WNA.
- Catatan data lama: tiket lama dengan menu_path `1>1`/`1>2` (antrean Bogor) akan berlabel judul menu baru di dashboard. Hanya label historis; tidak memengaruhi data.

### 3.5 Lokasi Kami (R6)
`config('kancil.office.other_locations')`:
- **MPP Kabupaten Banyumas**, Jl. Dr. Angka, Purwokerto
- **MPP Kabupaten Purbalingga**, Jl. Raya Mayjen Sungkono, Selabaya, Kec. Kalimanah

Tautan memakai format resmi Google Maps search (`https://www.google.com/maps/search/?api=1&query=…`), karena tautan *share* `maps.app.goo.gl` hanya bisa dibuat dari aplikasi Maps. **Perlu dicek petugas**; tautan share bisa ditempel di config tanpa ubah kode. Alamat dari pencarian web (jatengprov.go.id, pajak.go.id), perlu konfirmasi.

### 3.6 Cek Status Paspor + tempat permohonan (R5)
- Langkah 1 (prompt): "Silakan ketik *Nomor Permohonan* paspor Anda. Contoh: 1498000000012345" + daftar tempat permohonan (informasi, persis permintaan).
- Langkah 2: "Pilih tempat permohonan: 1 Imigrasi Cilacap, 2 MPP Banyumas, 3 MPP Purbalingga, 4 Ngapak Banyumas, 5 Ngapak Purbalingga" (+ 0 menu, 88 petugas, salah input = MSG-01..03).
- Satu pesan "1498… MPP Banyumas" langsung dicocokkan dan melompati langkah 2.
- Tiket antrean: "Cek status paspor, no. permohonan X, tempat permohonan: MPP Banyumas". Petugas melihatnya di dashboard.
- Daftar tempat di `config('kancil.application_places')`.

### 3.7 Konsistensi
- `BotScript::topicLabel`, `ExcelMatrixTest`, checklist uji ulang, dan dokumen PLAN diperbarui untuk nomor menu baru.
- Status FAQ 61 lama tetap dibiarkan (keputusan 21 Nov).

## 4. Urutan kerja (commit kecil, masing-masing dengan test)
1. `feat(faq)`: PSP-011/014 syarat SIPANTAS, PSP-016 Beda Data, PSP-015 & TRF-003 tanpa SIPANTAS penambahan nama; kontrak 3.1 diperbarui.
2. `feat(bot)`: blok PNBP terpisah (config + gate + contract test, tidak ada angka gabungan).
3. `feat(bot)`: menu Paspor 9 = Beda Data, geser 10–12.
4. `feat(bot)`: menu 2 sesuai Skenario 3.2 (M-09 tarif, M-10 pelaporan WNA meninggal, C6).
5. `feat(bot)`: cek status 2 langkah + tempat permohonan.
6. `feat(config)`: Lokasi Kami = Kantor + MPP Banyumas + MPP Purbalingga.
7. `test`: matriks Excel diperluas ke menu baru, E2E dashboard (tiket cek status menampilkan tempat), runbook/checklist.

## 5. Test
- **Unit:** pnbpBlock (format, tanpa angka gabungan), konsistensi config ↔ TRF FAQ, pencocok tempat permohonan (variasi ejaan: "mpp bms", "Ngapak Purbalingga", "imigrasi cilacap", "kanim"), kontrak 3.1 + deviasi, menu integrity (semua target valid, nomor berurutan, "kembali" = angka terakhir).
- **Feature:** skenario setiap jawaban Paspor 1–10 (isi + blok PNBP + kode FAQ), Beda Data, Penambahan Nama tanpa SIPANTAS; Menu 2: 6 tarif + 3 pelaporan + lokasi + navigasi kembali; eskalasi C6; cek status 2 langkah, 1 pesan, salah input 3x, 0/88 di tiap langkah; gate Pesan 5 untuk PSP-016 baru; faq-sync membuat PSP-016.
- **E2E webhook (ExcelMatrixTest):** semua jalur baru (1>9…1>12, 2>1>1…7, 2>2>1…4, 2>3) dengan echo gateway, tidak ada pesan yang tidak dibalas.
- **E2E Playwright:** tiket cek status dari webhook simulasi tampil di Antrean dengan "tempat permohonan"; jawaban Beda Data tampil di monitoring percakapan; halaman FAQ memuat PSP-016 sebagai Perlu Verifikasi.
- Mutation check: test PNBP harus gagal bila PSP-014 dikembalikan ke angka gabungan.

## 6. Non-teknis
- **R8:** minta kode pairing ke Mba Tami / Bu Lessa, lalu pairing device BalesOtomatis dengan nomor kantor. Setelah pairing, perbarui `BOT_OWN_NUMBERS` dan cek webhook.
- **Konfirmasi ke Imigrasi:** (a) alamat dan tautan peta MPP Banyumas dan Purbalingga; (b) jam layanan Ngapak (Sabtu–Minggu) untuk info tempat; (c) PSP-002 (penggantian) masih perlu konfirmasi dari audit sebelumnya.
- **Setelah deploy:** admin memverifikasi PSP-013..016 di dashboard. Uji ulang memakai checklist (diperbarui).
