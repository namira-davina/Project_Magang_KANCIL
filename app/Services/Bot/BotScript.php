<?php

namespace App\Services\Bot;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Teks menu, jawaban, dan pesan standar sesuai Dokumen Skenario FAQ
 * (M-00..M-10, MSG-01..09, ESK-01, PGD-01..10) dan Matriks Eskalasi (A1..C6).
 * Format tampilan mengikuti layanan WhatsApp Kantor Imigrasi Bogor: "Ketik *N* ...",
 * 0 = menu utama, 9 = menu sebelumnya; di jawaban 0 = kembali, 1 = hubungi Petugas.
 * Jalur internal (0>1>5 dst.) tetap memakai nomor lama agar log eskalasi tidak berubah.
 */
final class BotScript
{
    public const FOOTER = "Ketik *9* untuk kembali ke menu sebelumnya\nKetik *0* untuk kembali ke menu utama";

    public const DIVIDER = '------------------------';

    public const QUEUE_HELP = 'Ketik *antrean* untuk cek posisi, atau *batal* untuk membatalkan antrean.';

    /**
     * Menu: judul, jalur, induk, pilihan (angka => [label, tujuan]).
     * Tujuan: kode menu (M-xx), "ANS:<jalur>", "PGD-01", atau "ESK".
     */
    public const MENUS = [
        'M-00' => ['title' => null, 'path' => '', 'parent' => null, 'options' => [
            '1' => ['Pelayanan Paspor WNI', 'M-01'],
            '2' => ['Pelayanan Izin Tinggal (For Foreigners)', 'M-08'],
            '3' => ['Layanan Pengaduan (Complaint Support)', 'PGD-01'],
            '4' => ['Hubungi Petugas (Contact Officer)', 'ESK'],
        ]],
        'M-01' => ['title' => 'Pelayanan Paspor WNI', 'path' => '0', 'parent' => 'M-00', 'options' => [
            '1' => ['Persyaratan Paspor', 'M-02'],
            '2' => ['Cara Pembayaran Paspor', 'M-05'],
            '3' => ['Pengambilan Paspor', 'M-06'],
            '4' => ['Cek Status Permohonan', 'ANS:0>4'],
            '5' => ['Layanan Khusus', 'M-07'],
            '6' => ['Tarif Paspor', 'ANS:0>6'],
            '7' => ['Pengiriman Paspor via Pos', 'ANS:0>7'],
        ]],
        'M-02' => ['title' => 'Persyaratan Paspor', 'path' => '0>1', 'parent' => 'M-01', 'options' => [
            '1' => ['Paspor Baru', 'ANS:0>1>1'],
            '2' => ['Penggantian Paspor', 'ANS:0>1>2'],
            '3' => ['Berdasarkan Tujuan', 'M-03'],
            '4' => ['Paspor Anak', 'M-04'],
            '5' => ['Paspor Hilang', 'ANS:0>1>5'],
            '6' => ['Affidavit', 'ANS:0>1>6'],
        ]],
        'M-03' => ['title' => 'Paspor Berdasarkan Tujuan', 'path' => '0>1>3', 'parent' => 'M-02', 'options' => [
            '1' => ['Wisata', 'ANS:0>1>3>1'],
            '2' => ['Belajar', 'ANS:0>1>3>2'],
            '3' => ['Berobat', 'ANS:0>1>3>3'],
            '4' => ['CPMI', 'ANS:0>1>3>4'],
            '5' => ['Pelaut', 'ANS:0>1>3>5'],
            '6' => ['Umrah', 'ANS:0>1>3>6'],
            '7' => ['Haji', 'ANS:0>1>3>7'],
            '8' => ['Magang', 'ANS:0>1>3>8'],
        ]],
        'M-04' => ['title' => 'Paspor Anak', 'path' => '0>1>4', 'parent' => 'M-02', 'options' => [
            '1' => ['Syarat Umum', 'ANS:0>1>4>1'],
            '2' => ['Orang Tua Bercerai', 'ANS:0>1>4>2'],
            '3' => ['Orang Tua Meninggal / Yatim Piatu', 'ANS:0>1>4>3'],
            '4' => ['Anak Adopsi', 'ANS:0>1>4>4'],
        ]],
        'M-05' => ['title' => 'Cara Pembayaran Paspor', 'path' => '0>2', 'parent' => 'M-01', 'options' => [
            '1' => ['ATM', 'ANS:0>2>1'],
            '2' => ['M-Banking', 'ANS:0>2>2'],
            '3' => ['Aplikasi', 'ANS:0>2>3'],
            '4' => ['Kantor Pos / Indomaret / Alfamart', 'ANS:0>2>4'],
        ]],
        'M-06' => ['title' => 'Pengambilan Paspor', 'path' => '0>3', 'parent' => 'M-01', 'options' => [
            '1' => ['Diambil Sendiri', 'ANS:0>3>1'],
            '2' => ['Diwakilkan (satu KK)', 'ANS:0>3>2'],
            '3' => ['Diwakilkan (beda KK)', 'ANS:0>3>3'],
        ]],
        'M-07' => ['title' => 'Layanan Khusus', 'path' => '0>5', 'parent' => 'M-01', 'intro_faq' => 'LYK-001', 'options' => [
            '1' => ['KANCIL NARI', 'ANS:0>5>1'],
            '2' => ['KANCIL PERSAMI', 'ANS:0>5>2'],
            '3' => ['KANCIL PESAT', 'ANS:0>5>3'],
            '4' => ['Eazy Paspor', 'ANS:0>5>4'],
            '5' => ['KANCIL NGAPAK', 'ANS:0>5>5'],
            '6' => ['HOTS', 'ANS:0>5>6'],
        ]],
        'M-08' => ['title' => 'Pelayanan Izin Tinggal (For Foreigners)', 'path' => '1', 'parent' => 'M-00', 'options' => [
            '1' => ['Tarif Izin Tinggal dan Visa', 'M-09'],
            '2' => ['Pelaporan WNA Meninggal di Indonesia', 'M-10'],
        ]],
        'M-09' => ['title' => 'Tarif Izin Tinggal dan Visa', 'path' => '1>1', 'parent' => 'M-08', 'options' => [
            '1' => ['Izin Masuk Kembali', 'ANS:1>1>1'],
            '2' => ['Izin Tinggal Kunjungan', 'ANS:1>1>2'],
            '3' => ['Izin Tinggal Terbatas', 'ANS:1>1>3'],
            '4' => ['Izin Tinggal Tetap', 'ANS:1>1>4'],
            '5' => ['Exit Permit Only', 'ANS:1>1>5'],
            '6' => ['Visa Kunjungan Beberapa Kali', 'ANS:1>1>6'],
        ]],
        'M-10' => ['title' => 'Pelaporan WNA Meninggal', 'path' => '1>2', 'parent' => 'M-08', 'options' => [
            '1' => ['Pelapor dan Batas Waktu', 'ANS:1>2>1'],
            '2' => ['Dokumen', 'ANS:1>2>2'],
            '3' => ['Alur Pelaporan', 'ANS:1>2>3'],
        ]],
    ];

    /**
     * Jawaban per jalur (Bab 4): FAQ, tawaran 88, dan kode eskalasi bila 88 diketik.
     * Kode C1–C6 = kasus khusus; A4 = jawaban belum lengkap di materi.
     */
    public const ANSWERS = [
        '0>1>1' => ['faqs' => ['PSP-001'], 'offer88' => false, 'code' => null],
        '0>1>2' => ['faqs' => ['PSP-002'], 'offer88' => true, 'code' => 'A4'],
        '0>1>3>1' => ['faqs' => ['PSP-003'], 'offer88' => false, 'code' => null],
        '0>1>3>2' => ['faqs' => ['PSP-004'], 'offer88' => false, 'code' => null],
        '0>1>3>3' => ['faqs' => ['PSP-005'], 'offer88' => false, 'code' => null],
        '0>1>3>4' => ['faqs' => ['PSP-006'], 'offer88' => false, 'code' => null],
        '0>1>3>5' => ['faqs' => ['PSP-007'], 'offer88' => false, 'code' => null],
        '0>1>3>6' => ['faqs' => ['PSP-008'], 'offer88' => false, 'code' => null],
        '0>1>3>7' => ['faqs' => ['PSP-009'], 'offer88' => false, 'code' => null],
        '0>1>3>8' => ['faqs' => ['PSP-010'], 'offer88' => false, 'code' => null],
        '0>1>4>1' => ['faqs' => ['ANK-001', 'ANK-002', 'ANK-003', 'ANK-004'], 'offer88' => false, 'code' => null],
        '0>1>4>2' => ['faqs' => ['ANK-005'], 'offer88' => true, 'code' => 'C2'],
        '0>1>4>3' => ['faqs' => ['ANK-006', 'ANK-007', 'ANK-008'], 'offer88' => true, 'code' => 'C2'],
        '0>1>4>4' => ['faqs' => ['ANK-009'], 'offer88' => true, 'code' => 'C2'],
        '0>1>5' => ['faqs' => ['PSP-011'], 'offer88' => true, 'code' => 'C1'],
        '0>1>6' => ['faqs' => ['PSP-012'], 'offer88' => true, 'code' => 'C3'],
        '0>2>1' => ['faqs' => ['BYR-001'], 'offer88' => false, 'code' => null],
        '0>2>2' => ['faqs' => ['BYR-002'], 'offer88' => false, 'code' => null],
        '0>2>3' => ['faqs' => ['BYR-003'], 'offer88' => false, 'code' => null],
        '0>2>4' => ['faqs' => ['BYR-004'], 'offer88' => false, 'code' => null],
        '0>3>1' => ['faqs' => ['AMB-001'], 'offer88' => false, 'code' => null],
        '0>3>2' => ['faqs' => ['AMB-002'], 'offer88' => false, 'code' => null],
        '0>3>3' => ['faqs' => ['AMB-003'], 'offer88' => false, 'code' => null],
        '0>4' => ['faqs' => ['AMB-009'], 'offer88' => false, 'code' => null],
        '0>5>1' => ['faqs' => ['LYK-002'], 'offer88' => false, 'code' => null],
        '0>5>2' => ['faqs' => ['LYK-003'], 'offer88' => true, 'code' => 'A4'],
        '0>5>3' => ['faqs' => ['LYK-004', 'LYK-005'], 'offer88' => true, 'code' => 'C4'],
        '0>5>4' => ['faqs' => ['LYK-006', 'LYK-007', 'LYK-008'], 'offer88' => false, 'code' => null],
        '0>5>5' => ['faqs' => ['LYK-009'], 'offer88' => true, 'code' => 'A4'],
        '0>5>6' => ['faqs' => ['LYK-010', 'LYK-011'], 'offer88' => false, 'code' => null],
        '0>6' => ['faqs' => ['TRF-001', 'TRF-002', 'TRF-003', 'TRF-004', 'TRF-011'], 'offer88' => true, 'code' => 'A4'],
        '0>7' => ['faqs' => ['AMB-004', 'AMB-005', 'AMB-006', 'AMB-007', 'AMB-008'], 'offer88' => true, 'code' => 'C5'],
        '1>1>1' => ['faqs' => ['TRF-005', 'TRF-011'], 'offer88' => false, 'code' => null],
        '1>1>2' => ['faqs' => ['TRF-006', 'TRF-011'], 'offer88' => false, 'code' => null],
        '1>1>3' => ['faqs' => ['TRF-007', 'TRF-011'], 'offer88' => false, 'code' => null],
        '1>1>4' => ['faqs' => ['TRF-008', 'TRF-011'], 'offer88' => false, 'code' => null],
        '1>1>5' => ['faqs' => ['TRF-009', 'TRF-011'], 'offer88' => false, 'code' => null],
        '1>1>6' => ['faqs' => ['TRF-010', 'TRF-011'], 'offer88' => false, 'code' => null],
        '1>2>1' => ['faqs' => ['WNA-001', 'WNA-002'], 'offer88' => true, 'code' => 'C6'],
        '1>2>2' => ['faqs' => ['WNA-003'], 'offer88' => true, 'code' => 'C6'],
        '1>2>3' => ['faqs' => ['WNA-004'], 'offer88' => true, 'code' => 'C6'],
    ];

    /** Prioritas per kode eskalasi (Matriks Eskalasi Bagian 1). */
    public const PRIORITY = [
        'A1' => 'Rendah', 'A2' => 'Rendah', 'A3' => 'Sedang', 'A4' => 'Rendah',
        'C1' => 'Tinggi', 'C2' => 'Sedang', 'C3' => 'Sedang', 'C4' => 'Tinggi', 'C5' => 'Sedang', 'C6' => 'Tinggi',
    ];

    /** Kategori pengaduan PGD-03 => prioritas tiket (Matriks 5.3). */
    public const COMPLAINT_CATEGORIES = [
        '1' => ['Layanan paspor', 'Sedang'],
        '2' => ['Layanan WNA / izin tinggal', 'Sedang'],
        '3' => ['Pelayanan petugas', 'Tinggi'],
        '4' => ['Lainnya', 'Rendah'],
    ];

    public static function mainMenu(bool $aiHint = false, ?string $name = null, bool $greet = false): string
    {
        $text = $greet
            ? self::greeting($name)."\n\nSelamat Datang di Layanan Informasi dan Pengaduan *Kantor Imigrasi Kelas I TPI Cilacap*.\n\n"
            : "*Menu Utama*\n";

        $text .= "Silakan pilih menu di bawah ini:\n\n".self::optionLines('M-00');

        if ($aiHint) {
            $text .= "\n\nAnda juga dapat langsung mengetik pertanyaan.";
        }

        return $text."\n\nKetik angka pilihan Anda, lalu ikuti petunjuk selanjutnya\n------------\n_Type your selection, then follow the instructions_";
    }

    /** "Selamat Siang, Halo *Nama*" sesuai jam Asia/Jakarta. */
    public static function greeting(?string $name = null, ?CarbonInterface $at = null): string
    {
        $hour = Carbon::instance($at ?? now())->setTimezone('Asia/Jakarta')->hour;

        $part = match (true) {
            $hour >= 4 && $hour < 11 => 'Pagi',
            $hour >= 11 && $hour < 15 => 'Siang',
            $hour >= 15 && $hour < 18 => 'Sore',
            default => 'Malam',
        };

        $name = $name !== null ? trim(preg_replace('/[*_~`]/', '', $name)) : '';

        return "Selamat {$part}, Halo".($name !== '' ? " *{$name}*" : '');
    }

    public static function menu(string $code, ?string $intro = null): string
    {
        $menu = self::MENUS[$code];
        $text = '*'.$menu['title']."*\n";

        if ($intro) {
            $text .= $intro."\n\n";
        }

        $text .= "Silakan pilih topik yang ingin Anda ketahui lebih lanjut:\n\n"
            ."Ketik *0* untuk kembali ke menu utama\n"
            .self::optionLines($code);

        if (($menu['parent'] ?? 'M-00') !== 'M-00') {
            $text .= "\nKetik *9* untuk kembali ke menu sebelumnya";
        }

        return $text;
    }

    public static function optionLines(string $code): string
    {
        return collect(self::MENUS[$code]['options'])
            ->map(fn ($option, $key) => "Ketik *{$key}* {$option[0]}")
            ->implode("\n");
    }

    /** Jawaban FAQ dengan pembuka dan penutup gaya layanan informasi. */
    public static function answer(string $body): string
    {
        return "*Berikut ini adalah informasi dan solusi yang Anda cari:*\n\n".$body."\n\n".self::answerFooter();
    }

    public static function answerFooter(): string
    {
        return self::DIVIDER."\n\nButuh bantuan lebih lanjut?\n\n"
            ."*Ketik 0* untuk kembali ke menu sebelumnya\n"
            ."*Ketik 1* untuk menghubungi Petugas\n\n"
            .'*Terima kasih.*';
    }

    /** Pemohon dimasukkan ke antrean petugas; petugas membalas di chat yang sama. */
    public static function handoff(?string $ticket = null, ?int $queueNo = null, ?int $position = null): string
    {
        $text = "Baik, mohon menunggu sebentar.\n\nPermintaan Anda sedang kami hubungkan dengan *Petugas* dan masuk antrean.";

        if ($ticket === null) {
            return $text;
        }

        return $text."\n\n".self::queueLines($ticket, $queueNo, $position)
            ."\n\nPetugas akan membalas di chat ini sesuai urutan antrean.\n".self::QUEUE_HELP;
    }

    /** Balasan perintah "antrean" atau pengingat saat pemohon mengirim pesan selama menunggu. */
    public static function queueStatus(string $ticket, ?int $queueNo, ?int $position, bool $handling = false): string
    {
        if ($handling) {
            return "Tiket *{$ticket}* sedang ditangani *Petugas*. Mohon tunggu balasan di chat ini.";
        }

        return "Anda masih dalam antrean *Petugas*.\n\n".self::queueLines($ticket, $queueNo, $position)
            ."\n\nPesan Anda tetap kami teruskan ke petugas.\n".self::QUEUE_HELP;
    }

    public static function queueCancelled(string $ticket): string
    {
        return "Antrean tiket *{$ticket}* dibatalkan.";
    }

    public static function ticketResolved(string $ticket): string
    {
        return "Tiket *{$ticket}* telah diselesaikan oleh Petugas. Terima kasih telah menghubungi *Kantor Imigrasi Kelas I TPI Cilacap*.\n\n"
            .'Ketik *0* untuk kembali ke menu utama.';
    }

    /** Petugas mengalihkan tiket antrean menjadi pengaduan resmi. */
    public static function ticketToComplaint(string $ticket, string $complaint): string
    {
        return "Permintaan Anda pada tiket *{$ticket}* telah kami catat sebagai *pengaduan resmi*.\n\n"
            ."Nomor tiket pengaduan: *{$complaint}*\n_Status awal tiket: Baru._\n\n"
            ."Tanggapan akan dikirim ke chat ini. Cek status kapan saja lewat menu *3* (Layanan Pengaduan) lalu *2* (Cek status pengaduan).\n\n"
            .'Ketik *0* untuk kembali ke menu utama';
    }

    public static function ticketExpired(string $ticket): string
    {
        return "Mohon maaf, tiket *{$ticket}* belum dapat dilayani Petugas dan ditutup otomatis.\n\n"
            ."Ketik *0* untuk kembali ke menu utama\nKetik *88* untuk masuk antrean Petugas kembali";
    }

    private static function queueLines(string $ticket, ?int $queueNo, ?int $position): string
    {
        $lines = ["Nomor tiket: *{$ticket}*"];

        if ($queueNo !== null) {
            $lines[] = "Nomor antrean hari ini: *{$queueNo}*";
        }

        if ($position !== null) {
            $lines[] = "Posisi antrean Anda: *ke-{$position}*".($position === 1 ? ' (berikutnya dilayani)' : '');
        }

        return implode("\n", $lines);
    }

    public static function msg(string $id, ?string $fill = null): string
    {
        return match ($id) {
            'MSG-01' => 'Mohon maaf, pilihan Anda tidak tersedia. Silakan ketik angka sesuai menu di bawah ini.',
            'MSG-02' => 'Mohon maaf, pilihan Anda masih belum sesuai. Silakan ketik angka pilihan saja, misalnya *1*.',
            'MSG-03' => "Mohon maaf, kami belum dapat memahami pesan Anda.\n\nKetik *0* untuk kembali ke menu utama\nKetik *88* untuk menghubungi Petugas",
            'MSG-04' => 'Mohon maaf, layanan ini hanya menerima pesan teks berupa angka pilihan. Silakan ketik angka sesuai menu.',
            'MSG-05' => 'Mohon maaf, informasi untuk topik ini sedang diperbarui.',
            'MSG-06' => 'Sesi sebelumnya telah berakhir.',
            'MSG-07' => '_Saat ini di luar jam layanan petugas. Petugas akan membalas pesan Anda pada jam layanan'.($fill ? ": {$fill}._" : ' berikutnya._'),
            'MSG-08' => 'Pengaduan dibatalkan. Anda dapat memulai kembali kapan saja.',
            'MSG-09' => 'Sesi sebelumnya telah berakhir, dan pengaduan yang belum dikirim tidak tersimpan. Pilih *3* (Layanan Pengaduan) untuk memulai lagi.',
            'AI-UNKNOWN' => 'Mohon maaf, pertanyaan Anda belum dapat dijawab otomatis.',
            'AI-DISCLAIMER' => '_Informasi umum. Untuk kepastian, silakan hubungi petugas._',
            default => throw new \InvalidArgumentException("Pesan {$id} tidak dikenal."),
        };
    }

    public static function pgd(string $id, array $data = []): string
    {
        return match ($id) {
            'PGD-01' => "*Layanan Pengaduan*\nSilakan pilih menu di bawah ini:\n\n"
                ."Ketik *0* untuk kembali ke menu utama\nKetik *1* Buat pengaduan baru\nKetik *2* Cek status pengaduan",
            'PGD-02' => "Silakan ketik *nama lengkap* Anda.\n\n_Ketik *batal* untuk membatalkan pengaduan._",
            'PGD-03' => "Pilih kategori pengaduan:\n\n".collect(self::COMPLAINT_CATEGORIES)
                ->map(fn ($c, $k) => "Ketik *{$k}* {$c[0]}")->implode("\n")
                ."\n\nKetik *9* untuk kembali ke langkah sebelumnya",
            'PGD-04' => 'Silakan ketik isi pengaduan Anda (kronologi singkat).',
            'PGD-04-SHORT' => 'Isi pengaduan terlalu singkat (minimal '.config('kancil.complaint.min_length').' karakter). Silakan ketik ulang isi pengaduan Anda dengan lebih lengkap.',
            'PGD-05' => "Apakah Anda ingin melampirkan foto atau dokumen?\n\nKetik *1* Ya\nKetik *2* Tidak",
            'PGD-06' => 'Silakan kirim foto atau dokumen (maksimal '.config('kancil.complaint.max_attachments').' berkas). Ketik *selesai* jika sudah.',
            'PGD-06-ACK' => "Berkas ke-{$data['n']} diterima. Kirim berkas lain atau ketik *selesai* jika sudah.",
            'PGD-06-FULL' => 'Batas '.config('kancil.complaint.max_attachments').' berkas sudah tercapai. Ketik *selesai* untuk melanjutkan.',
            'PGD-07' => "*Ringkasan pengaduan Anda*\n\nNama: {$data['name']}\nKategori: {$data['category']}\nIsi: {$data['text']}\nLampiran: "
                .($data['attachments'] > 0 ? "{$data['attachments']} berkas" : 'tidak ada')
                ."\n\nKetik *1* Kirim pengaduan\nKetik *2* Batalkan",
            'PGD-08' => "Terima kasih, pengaduan Anda sudah kami terima.\n\nNomor tiket: *{$data['ticket']}*\n_Status awal tiket: Baru._\n\n"
                ."Cek status kapan saja lewat menu *3* (Layanan Pengaduan) lalu *2* (Cek status pengaduan).\n\n"
                .'Ketik *0* untuk kembali ke menu utama',
            'PGD-09' => "Silakan ketik nomor tiket Anda.\n\n".self::FOOTER,
            'PGD-10' => "Status pengaduan *{$data['ticket']}*: *{$data['status']}*."
                .($data['response'] ? "\nTanggapan petugas: {$data['response']}" : '')
                ."\n\n".self::FOOTER,
            'PGD-10-NOTFOUND' => "Nomor tiket tidak ditemukan untuk nomor WhatsApp ini. Pastikan nomor tiket benar (contoh: ADU-202611-0001).\n\n".self::FOOTER,
            default => throw new \InvalidArgumentException("Pesan {$id} tidak dikenal."),
        };
    }

    /** Label topik untuk petugas dari jalur menu (mis. 0>1>5 => "Persyaratan Paspor › Paspor Hilang"). */
    public static function topicLabel(?string $path): string
    {
        if ($path === null || $path === '') {
            return 'Umum';
        }

        if ($path === 'AI') {
            return 'Pertanyaan bebas (AI)';
        }

        if ($path === '3') {
            return 'Hubungi Petugas (menu utama)';
        }

        foreach (self::MENUS as $menu) {
            foreach ($menu['options'] as $option) {
                if ($option[1] === 'ANS:'.$path) {
                    return ($menu['title'] ? $menu['title'].' › ' : '').$option[0];
                }
            }
        }

        foreach (self::MENUS as $menu) {
            if ($menu['path'] === $path && $menu['title']) {
                return $menu['title'];
            }
        }

        return $path;
    }

    /** Kode jawaban (jalur) yang memuat FAQ tertentu. */
    public static function pathForFaq(string $faqCode): ?string
    {
        foreach (self::ANSWERS as $path => $answer) {
            if (in_array($faqCode, $answer['faqs'], true)) {
                return $path;
            }
        }

        return null;
    }
}
