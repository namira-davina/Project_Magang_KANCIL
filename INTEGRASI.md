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
                                    │    petugas menangani? → bot diam
                                    │    BotEngine: menu M-00..M-10, 9/99/88, salah input 3x,
                                    │    sesi 30 menit, MSG-05 (FAQ belum aktif), pengaduan PGD-01..10,
                                    │    pertanyaan bebas → AI (hanya FAQ, wajib kode FAQ, fallback ESK-01)
                                    └─ BOT_ENGINE=balesotomatis: AI BalesOtomatis menjawab, Laravel mencatat
                                              ▼
                                    SendWhatsappMessage → send_personal_message
```

Eskalasi dicatat dengan kode Matriks Eskalasi (A1–A4, B0/B1, C1–C6) dan prioritasnya.
Dashboard: Percakapan (inbox, balas, ambil alih/kembalikan ke bot), Pengaduan (status + tanggapan terkirim ke WA),
Laporan (bulanan, eskalasi per kode), FAQ (centang **Aktif ditampilkan bot** setelah Terverifikasi).

## Setup

1. `.env` — isi `BALESOTOMATIS_API_KEY` (Kode Akses) dan `LLM_API_KEY` (SumoPod). Lihat `.env.example`.
2. `php artisan migrate --seed` — termasuk 61 FAQ dari Dokumen Inventarisasi (status *perlu verifikasi*).
3. `php artisan kancil:balesotomatis-info` — salin `number_id` ke `BALESOTOMATIS_NUMBER_ID`, dan URL webhook
   ke member area BalesOtomatis: Integration → Pengaturan Webhook.
4. `php artisan kancil:ai-cek` — cek koneksi, saldo SumoPod, dan jumlah FAQ yang boleh dipakai AI.
5. Bila `BOT_ENGINE=laravel`: **matikan AI Powered** di BalesOtomatis agar pemohon tidak dibalas dua kali.
6. Produksi: worker antrean (`php artisan queue:work`, via Supervisor/systemd) dan cron
   `* * * * * php artisan schedule:run` (lepas ambil alih kedaluwarsa, eskalasi tiket terlambat, bersihkan webhook).

## Perintah

| Perintah | Fungsi |
| --- | --- |
| `kancil:simulasi "Halo"` | Uji bot dari terminal tanpa WhatsApp (tanpa argumen = interaktif, `--reset` = sesi baru) |
| `kancil:buat-user agus --role=petugas` | Buat akun Admin / Petugas / Pimpinan |
| `kancil:ai-cek [--saldo]` | Cek AI SumoPod |
| `kancil:balesotomatis-info` | Info perangkat + URL webhook |
| `kancil:pemeliharaan` | Tugas berkala (juga terjadwal tiap 5 menit) |

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
