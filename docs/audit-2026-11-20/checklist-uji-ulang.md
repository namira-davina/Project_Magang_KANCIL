# Checklist Uji Ulang (pasca-deploy) — Audit 20 November 2026

Dipakai petugas di WhatsApp asli, mengikuti format `Testing_Kancil Sapa.xlsx`. Kolom **Harapan** = balasan yang harus muncul.
Isi kolom **Respons (detik)** dan **Hasil** (baik / tidak baik). Bot harus **selalu** membalas; jika diam, catat jam dan nomor WA.

Sebelum mulai: pastikan "AI Powered" di member area BalesOtomatis **mati**, dan PSP-013/014/015 sudah diverifikasi di dashboard (lihat runbook langkah 5). Kalau belum, menu 1→5, 1→8, dan 1→9 akan menampilkan Pesan 5.

## A. Menu utama
| No | Ketik | Harapan | Respons (detik) | Hasil |
|---|---|---|---|---|
| A1 | Halo | Sapaan + "Status Hari Ini: BUKA/TUTUP/LIBUR" + pilihan 1/2/3 | | |
| A2 | abc (3x) | 1: "Maaf, pilihan tidak valid…", 2: "…masih belum sesuai…", 3: "Mohon maaf, bot belum dapat memahami pesan Anda. Ketik 88 … Ketik 0 …" | | |
| A3 | kirim foto | "Maaf, bot hanya menerima pesan teks berupa angka pilihan…" + menu | | |

## B. Menu 1 Paspor (ulangi tiap topik 1–9: ketik jawaban, lalu **0**; ulangi lagi lalu **1**)
| No | Jalur | Harapan isi (Inventarisasi 3.1) | 0 → menu utama | 1 → antrean petugas |
|---|---|---|---|---|
| B1 | 1 → 1 Biaya | Rp650.000 / Rp950.000, percepatan Rp1.000.000, denda hilang/rusak, SIPANTAS, PP 45/2024 | | |
| B2 | 1 → 2 Paspor Baru | KTP elektronik, KK, akta/ijazah/buku nikah; berkas asli + fotokopi A4 | | |
| B3 | 1 → 3 Penggantian | KTP elektronik, KK, akta/ijazah/buku nikah, paspor lama; daftar M-Paspor | | |
| B4 | 1 → 4 Paspor Anak | Syarat umum, **anak < 5 tahun tanpa M-Paspor**, 5–17 tahun wajib M-Paspor, masa berlaku 5 tahun, kasus cerai/meninggal/adopsi | | |
| B5 | 1 → 5 M-Paspor | Tautan Android/iOS + langkah (PSP-013) | | |
| B6 | 1 → 6 Prioritas | KANCIL NARI: ibu hamil, disabilitas, anak < 5, lansia > 60, antrean kode C | | |
| B7 | 1 → 7 Hilang | Syarat + denda Rp1.000.000 + SIPANTAS | | |
| B8 | 1 → 8 Rusak | Syarat + PNBP 1.150.000/1.450.000 + SIPANTAS | | |
| B9 | 1 → 9 Penambahan Nama | Syarat + SIPANTAS | | |
| B10 | 1 → 10 → 0 | Minta nomor permohonan → menu utama | | |
| B11 | 1 → 10 → 88 | Masuk antrean petugas | | |
| B12 | 1 → 11 | Menu utama | | |

Setelah "1 → petugas": ketik **1** (posisi antrean), **2** (batal → menu), atau **0** (menu, tiket tetap).
**Fokus regresi Excel Sheet1:** B7 dan B8 lalu 0/1 harus dibalas.

## C. Menu 2 Izin Tinggal
| No | Jalur | Harapan |
|---|---|---|
| C1 | 2 → 1 | Antrean petugas (+ Pesan 7 bila di luar jam) |
| C2 | 2 → 2 | Antrean petugas |
| C3 | 2 → 3 → 0 / 1 | Lokasi Kantor + MPP Cilacap → menu / antrean |
| C4 | 2 → 4 | Menu utama |

## D. Menu 3 Pengaduan (4 kategori, sesuai "4 kategori pengaduan.pdf")
Untuk tiap kategori (Layanan paspor / WNA-izin tinggal / Pelayanan petugas / Lainnya):
1. Pemohon: Halo → 3 → tulis keluhan.
2. Petugas: Antrean → Ambil → balas → **Jadikan Pengaduan** (pilih kategori). Alert harus berbunyi "Pemohon sudah diberi tahu…". Pemohon menerima "telah kami catat sebagai pengaduan resmi" **tanpa** nomor tiket.
3. Pengaduan → Diproses + tanggapan → Selesai + tanggapan. Pemohon menerima kedua tanggapan.
4. Prioritas di dashboard: Sedang / Sedang / **Tinggi** / Rendah. Pengaduan "Pelayanan petugas" tidak terlihat oleh akun petugas biasa.

## E. Di luar jam layanan (Sabtu/Minggu/libur/≥ 16.00, Jumat ≥ 16.30)
- **E1** Halo → 3: pesan antrean + "Saat ini di luar jam layanan petugas. Jam layanan: Senin–Kamis … Jumat …".

## F. Dashboard
- **F1** Antrean: kartu "Rata-rata tunggu hari ini" wajar (tidak ratusan menit) dan berketerangan "Dari tiket masuk hari ini…".
- **F2** Pengaduan: label "Menampilkan 1–10 dari N tiket", halaman 2 benar.
- **F3** Database FAQ: label "dari 64 entri", tidak ada tombol halaman "7" palsu.
