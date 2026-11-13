# KANCIL SAPA — Integrasi WhatsApp & AI

Chatbot WhatsApp + dashboard Kantor Imigrasi Kelas I TPI Cilacap.
WhatsApp lewat **BalesOtomatis**, AI lewat **SumoPod** (kompatibel OpenAI, model `gpt-5-nano`).

## Alur

```
Pemohon ──WA──> BalesOtomatis ──webhook──> /webhook/balesotomatis/{token}
                                              │ simpan webhook_events (idempoten), balas 200
                                              ▼ antrean
                                    ProcessIncomingChat
                                    ├─ simpan pesan + lampiran (disk privat)
                                    ├─ BOT_ENGINE=laravel:
                                    │    petugas menangani / pemohon di antrean petugas? → bot diam
                                    │    BotEngine: menu M-00..M-10 gaya Imigrasi Bogor ("Ketik *N* ..."),
                                    │    0 = menu utama, 9 = menu sebelumnya, setelah jawaban 0 = kembali / 1 = Petugas,
                                    │    99/88 tetap jalan pintas, salah input 3x,
                                    │    sesi 30 menit, MSG-05 (FAQ belum aktif), pengaduan PGD-01..10,
                                    │    pertanyaan bebas → AI (hanya FAQ, wajib kode FAQ, fallback ESK-01)
                                    └─ BOT_ENGINE=balesotomatis: AI BalesOtomatis menjawab, Laravel mencatat
                                              ▼
                                    SendWhatsappMessage → send_personal_message
```

Hubungi Petugas (menu 4, "1" setelah jawaban, 88, atau AI tidak tahu jawaban) tidak menyebut nomor/email kantor.
Pemohon masuk **antrean tiket petugas** (FIFO, yang lebih dulu bertanya dilayani lebih dulu):

- Bot membalas nomor tiket `LYN-YYYYMMDD-NNN`, nomor antrean hari ini, dan posisi antrean.
- Status percakapan *Menunggu Petugas*; bot diam. Selama menunggu pemohon bisa mengetik **antrean**
  (cek posisi, maks. 1x/menit) atau **batal** (keluar antrean, kembali ke menu). Pesan lain dicatat untuk petugas
  dan dibalas pengingat posisi paling sering tiap `TICKET_NOTICE_MINUTES` (15).
- Petugas: halaman **Antrean Petugas** → *Ambil Antrean Berikutnya* (tiket tertua) atau *Ambil* tiket tertentu,
  lalu membalas di Percakapan. Membalas/ambil alih dari Percakapan juga mengambil tiket. Petugas tidak bisa
  menyerobot percakapan yang dipegang petugas lain (409); Admin bisa.
- *Selesaikan Tiket* (Antrean atau tombol Kembalikan ke Bot) mengirim pesan penutup dan bot aktif lagi.
- Tiket menunggu yang tidak diambil `TICKET_EXPIRE_BUSINESS_DAYS` (2) hari kerja ditutup *Kedaluwarsa*
  oleh `kancil:pemeliharaan` dan pemohon diberi tahu. Masa ambil alih (`BOT_HANDOFF_MINUTES`) yang habis
  menutup tiket yang sedang ditangani sebagai *Selesai*.
- Status tiket: Menunggu → Ditangani → Selesai; Menunggu → Batal / Kedaluwarsa.
- *Jadikan Pengaduan*: bila ternyata keluhan, petugas mengalihkan tiket `LYN` menjadi pengaduan resmi `ADU`
  (pilih kategori + isi). Tiket antrean ditutup, pemohon menerima nomor pengaduan.

**Antrean vs Pengaduan.** Antrean (`LYN`) = pemohon bertanya dan ingin dilayani petugas sekarang lewat chat (menit/jam).
Pengaduan (`ADU`, menu 3) = keluhan resmi dengan kategori, kronologi, lampiran; ditindaklanjuti Admin/Pimpinan di
halaman Pengaduan dengan batas waktu hari kerja per prioritas dan eskalasi ke Pimpinan. Pelapor diberi tahu setiap ada
tanggapan atau perubahan status. Lampiran pengaduan bisa dibuka dari panel detail.
- Opsi A (`BOT_ENGINE=balesotomatis`): tiket dicatat tanpa pesan ke pemohon (AI BalesOtomatis yang membalas).

Eskalasi dicatat dengan kode Matriks Eskalasi (A1–A4, B0/B1, C1–C6) dan prioritasnya.
Dashboard: Percakapan (inbox, balas, ambil alih/kembalikan ke bot), Antrean Petugas (FIFO, ambil, selesaikan), Pengaduan (status + tanggapan terkirim ke WA),
Laporan (bulanan, eskalasi per kode), FAQ (centang **Aktif ditampilkan bot** setelah Terverifikasi).

## Setup

1. `.env` — isi `BALESOTOMATIS_API_KEY` (Kode Akses) dan `LLM_API_KEY` (SumoPod). Lihat `.env.example`.
2. `php artisan migrate --seed` — termasuk 61 FAQ dari Dokumen Inventarisasi (status *perlu verifikasi*).
3. `php artisan kancil:balesotomatis-info` — salin `number_id` ke `BALESOTOMATIS_NUMBER_ID`, dan URL webhook
   ke member area BalesOtomatis: Integration → Pengaturan Webhook.
4. `php artisan kancil:ai-cek` — cek koneksi, saldo SumoPod, dan jumlah FAQ yang boleh dipakai AI.
5. Bila `BOT_ENGINE=laravel`: **matikan AI Powered** di BalesOtomatis agar pemohon tidak dibalas dua kali.
6. Produksi: worker antrean (`php artisan queue:work`, via Supervisor/systemd) dan cron
   `* * * * * php artisan schedule:run` (lepas ambil alih kedaluwarsa, tutup antrean basi, eskalasi tiket terlambat, bersihkan webhook).

## Perintah

| Perintah | Fungsi |
| --- | --- |
| `kancil:simulasi "Halo"` | Uji bot dari terminal tanpa WhatsApp (tanpa argumen = interaktif, `--reset` = sesi baru) |
| `kancil:buat-user agus --role=petugas` | Buat akun Admin / Petugas / Pimpinan |
| `kancil:ai-cek [--saldo]` | Cek AI SumoPod |
| `kancil:balesotomatis-info` | Info perangkat + URL webhook |
| `kancil:pemeliharaan` | Tugas berkala (juga terjadwal tiap 5 menit) |
| `kancil:gabung-percakapan [--dry-run]` | Gabungkan percakapan ganda dengan nomor WA sama (sisa bug chat_id @lid vs @s.whatsapp.net) |

## Role

| Role | Akses |
| --- | --- |
| admin | semua halaman |
| petugas | dashboard, percakapan (balas/ambil alih), pengaduan (baca, tanpa kategori *Pelayanan petugas*), pengaturan |
| pimpinan | dashboard, percakapan (baca), pengaduan (semua, ubah status), laporan, pengaturan |

## AI

- Hanya FAQ **Terverifikasi + Aktif** yang dipakai (`LLM_FAQ_SCOPE=verified`). `LLM_FAQ_SCOPE=all` memakai semua FAQ non-draf
  dengan catatan "Informasi umum".
- Jawaban wajib menyebut kode FAQ yang valid; bila tidak yakin → pesan kontak petugas (ESK-01) dan log A1.
- NIK/nomor panjang dan email disamarkan sebelum dikirim ke SumoPod. Semua panggilan tercatat di `llm_logs`.
- Batas biaya: `LLM_DAILY_REQUEST_LIMIT`. Gagal/limit → bot kembali ke menu rule-based.

## Test

```
php artisan test
```
