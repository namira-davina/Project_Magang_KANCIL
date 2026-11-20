<?php

namespace App\Services\Bot;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Teks menu, jawaban, dan pesan standar. Menu dan jawaban paspor/izin tinggal meniru persis layanan WhatsApp
 * Kantor Imigrasi Kelas I Khusus Non TPI Bogor (kecuali Drive Thru dan persyaratan berdasarkan tujuan);
 * hanya identitas kantor (nama, alamat, jam, lokasi) memakai data Cilacap. Pilihan "Ketik *N* ...", 0 = menu utama;
 * setelah jawaban 0 = menu utama, 1 = hubungi Petugas. Pesan MSG/PGD/eskalasi mengikuti Matriks Eskalasi (A1..C6).
 */
final class BotScript
{
    /** Footer navigasi standar pada setiap menu bertingkat. */
    public const FOOTER = "Ketik *9* untuk kembali ke menu sebelumnya\nKetik *0* untuk kembali ke menu utama";

    /** Pemisah visual antara isi jawaban dan footer aksi. */
    public const DIVIDER = '------------------------';

    /** Pilihan pemohon selama menunggu Petugas (bot tetap aktif, tidak diam). */
    public const QUEUE_OPTIONS = "Sambil menunggu, Anda dapat:\nKetik *0* kembali ke menu utama\nKetik *1* cek posisi antrean\nKetik *2* batalkan antrean";

    /**
     * Menu: judul (hanya untuk label petugas), jalur, induk, pilihan (angka => [label, tujuan]).
     * Tujuan: kode menu (M-xx), "ANS:<jalur>", "STATUS:<jalur>", "ESK:<jalur>" (masuk antrean petugas),
     * "BACK" (menu sebelumnya) atau "PGD-01" (alur pengaduan bot, tidak dipakai di menu utama gaya Bogor).
     * Urutan dan teks mengikuti layanan WhatsApp Imigrasi Bogor, tanpa Drive Thru dan tanpa persyaratan berdasarkan tujuan.
     */
    public const MENUS = [
        'M-00' => ['title' => null, 'path' => '', 'parent' => null, 'options' => [
            '1' => ['Pelayanan Paspor WNI', 'M-01'],
            '2' => ['Pelayanan Izin Tinggal (For Foreigners)', 'M-08'],
            '3' => ['Layanan Pengaduan (Complaint Support)', 'ESK:3'],
        ]],
        'M-01' => ['title' => 'Pelayanan Paspor WNI', 'path' => '0', 'parent' => 'M-00', 'options' => [
            '1' => ['Biaya Paspor', 'ANS:0>1'],
            '2' => ['Persyaratan Paspor Baru', 'ANS:0>2'],
            '3' => ['Persyaratan Penggantian Paspor', 'ANS:0>3'],
            '4' => ['Persyaratan Paspor Anak', 'ANS:0>4'],
            '5' => ['Pendaftaran Paspor (M-Paspor)', 'ANS:0>5'],
            '6' => ['Layanan Prioritas Ramah HAM', 'ANS:0>6'],
            '7' => ['Permohonan Paspor Hilang', 'ANS:0>7'],
            '8' => ['Permohonan Paspor Rusak', 'ANS:0>8'],
            '9' => ['Penambahan Nama pada Paspor', 'ANS:0>9'],
            '10' => ['Cek Status Paspor', 'STATUS:0>10'],
            '11' => ['untuk kembali ke menu sebelumnya', 'BACK'],
        ]],
        'M-08' => ['title' => 'Pelayanan Izin Tinggal (For Foreigners)', 'path' => '1', 'parent' => 'M-00', 'options' => [
            '1' => ['Izin Tinggal (Residence Permit)', 'ESK:1>1'],
            '2' => ['Pengaduan Orang Asing (Foreigner Complaint)', 'ESK:1>2'],
            '3' => ['Lokasi Kami', 'ANS:1>3'],
            '4' => ['untuk kembali ke menu sebelumnya', 'BACK'],
        ]],
    ];

    /**
     * Jawaban per jalur menu Bogor. 'faq' = kode FAQ sumber (Inventarisasi 3.1, urut tampil); isi diambil dari tabel FAQ
     * lewat MenuAnswerResolver dan tunduk pada gate status (belum Aktif = Pesan 5). 'faq' null = dinamis (locationText()).
     * 'code' = kode Matriks Eskalasi bila pemohon memilih "hubungi Petugas" setelah jawaban itu (C1 = paspor hilang, C2 = anak).
     */
    public const ANSWERS = [
        '0>1' => ['faq' => ['TRF-001', 'TRF-002', 'TRF-004', 'TRF-003', 'TRF-011'], 'code' => null],
        '0>2' => ['faq' => ['PSP-001'], 'code' => null],
        '0>3' => ['faq' => ['PSP-002'], 'code' => null],
        '0>4' => ['faq' => ['ANK-001', 'ANK-002', 'ANK-003', 'ANK-004', 'ANK-005', 'ANK-006', 'ANK-007', 'ANK-008', 'ANK-009'], 'code' => 'C2'],
        '0>5' => ['faq' => ['PSP-013'], 'code' => null],
        '0>6' => ['faq' => ['LYK-002'], 'code' => null],
        '0>7' => ['faq' => ['PSP-011'], 'code' => 'C1'],
        '0>8' => ['faq' => ['PSP-014'], 'code' => null],
        '0>9' => ['faq' => ['PSP-015'], 'code' => null],
        '1>3' => ['faq' => null, 'code' => null],
    ];

    /** Kode Matriks Eskalasi bila pemohon minta Petugas setelah AI menjawab dari FAQ ini (kode FAQ pertama yang dipakai). */
    public const FAQ_ESCALATION = [
        'PSP-002' => 'A4', 'PSP-011' => 'C1', 'PSP-012' => 'C3',
        'ANK-005' => 'C2', 'ANK-006' => 'C2', 'ANK-007' => 'C2', 'ANK-008' => 'C2', 'ANK-009' => 'C2',
        'LYK-003' => 'A4', 'LYK-004' => 'C4', 'LYK-005' => 'C4', 'LYK-009' => 'A4', 'TRF-001' => 'A4',
        'AMB-004' => 'C5', 'AMB-005' => 'C5', 'AMB-006' => 'C5', 'AMB-007' => 'C5', 'AMB-008' => 'C5',
        'WNA-001' => 'C6', 'WNA-002' => 'C6', 'WNA-003' => 'C6', 'WNA-004' => 'C6',
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

    /** Menu utama: sapaan + identitas kantor + status hari ini + pilihan (format Imigrasi Bogor). */
    public static function mainMenu(?string $name = null, string $status = ''): string
    {
        $office = (string) config('kancil.office.name', 'Kantor Imigrasi Kelas I TPI Cilacap');

        return self::greeting($name)."\n\nSelamat Datang , di Layanan Informasi dan Pengaduan {$office}.\n\n"
            .($status !== '' ? $status."\n\n" : '')
            ."Silakan pilih menu di bawah ini:\n\n".self::optionLines('M-00')
            ."\n\nKetik angka pilihan Anda, lalu ikuti petunjuk selanjutnya\n------------\n_Type your selection, then follow the instructions_";
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

    /** Submenu tanpa judul, persis seperti Bogor: "Silakan pilih topik ..." lalu daftar pilihan. */
    public static function menu(string $code): string
    {
        return "Silakan pilih topik yang ingin Anda ketahui lebih lanjut:\n\n"
            ."Ketik *0* untuk kembali ke menu utama\n"
            .self::optionLines($code);
    }

    public static function optionLines(string $code): string
    {
        return collect(self::MENUS[$code]['options'])
            ->map(fn ($option, $key) => "Ketik *{$key}* {$option[0]}")
            ->implode("\n");
    }

    /** Prompt nomor permohonan untuk Cek Status Paspor. */
    public static function statusPrompt(): string
    {
        return "Silakan ketik *Nomor Permohonan* paspor Anda.\nContoh: 1498000000012345\n\nKetik *0* untuk kembali ke menu utama.";
    }

    /** Lokasi Kami: identitas kantor Cilacap (config kancil.office). */
    public static function locationText(): string
    {
        $office = config('kancil.office');
        $lines = ["📍 {$office['name']}\n{$office['address']}\n{$office['maps']}"];

        foreach ($office['other_locations'] ?? [] as $name => $maps) {
            $lines[] = "📍 {$name}\n{$maps}";
        }

        return implode("\n\n", $lines);
    }

    /** Jawaban dengan pembuka dan penutup gaya layanan informasi. */
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

    /** Pemohon diteruskan ke Petugas: nomor antrean, posisi, dan pilihan selama menunggu (nomor tiket tidak ditampilkan). */
    public static function handoff(?int $queueNo = null, ?int $position = null): string
    {
        $lines = self::queueLines($queueNo, $position);

        return '🎫 *Permintaan Anda diteruskan ke Petugas*

'.($lines !== '' ? $lines.'

' : '')
            .'Petugas akan membalas di chat ini sesuai urutan antrean.

'.self::QUEUE_OPTIONS;
    }

    /** Balasan cek posisi atau pengingat saat pemohon mengirim pesan selama menunggu. */
    public static function queueStatus(?int $queueNo, ?int $position, bool $handling = false): string
    {
        if ($handling) {
            return 'Permintaan Anda sedang ditangani *Petugas*. Mohon tunggu balasan di chat ini.';
        }

        $lines = self::queueLines($queueNo, $position);

        return '🎫 Anda masih dalam antrean *Petugas*.

'.($lines !== '' ? $lines.'

' : '')
            .'Pesan Anda tetap kami teruskan ke Petugas.

'.self::QUEUE_OPTIONS;
    }

    public static function queueCancelled(): string
    {
        return 'Antrean Anda ke Petugas dibatalkan.';
    }

    public static function ticketResolved(): string
    {
        return 'Permintaan Anda telah diselesaikan oleh Petugas. Terima kasih telah menghubungi *Kantor Imigrasi Kelas I TPI Cilacap*.

'
            .'Ketik *0* untuk kembali ke menu utama.';
    }

    /** Petugas mengalihkan tiket antrean menjadi pengaduan resmi. */
    public static function ticketToComplaint(): string
    {
        return 'Permintaan Anda telah kami catat sebagai *pengaduan resmi*.

'
            .'Tanggapan Petugas akan dikirim ke chat ini.

'
            .'Ketik *0* untuk kembali ke menu utama';
    }

    public static function ticketExpired(): string
    {
        return 'Mohon maaf, permintaan Anda belum dapat dilayani Petugas dan antrean ditutup otomatis.

'
            .'Ketik *0* untuk kembali ke menu utama
Ketik *88* untuk masuk antrean Petugas kembali';
    }

    private static function queueLines(?int $queueNo, ?int $position): string
    {
        $lines = [];

        if ($queueNo !== null) {
            $lines[] = "Nomor antrean hari ini: *{$queueNo}*";
        }

        if ($position !== null) {
            $lines[] = "Posisi antrean Anda: *ke-{$position}*".($position === 1 ? ' (berikutnya dilayani)' : '');
        }

        return implode('
', $lines);
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
            return 'Layanan Pengaduan (menu utama)';
        }

        foreach (self::MENUS as $menu) {
            foreach ($menu['options'] as $option) {
                if (str_ends_with($option[1], ':'.$path)) {
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
}
