<?php

namespace App\Services\Bot;

/**
 * Teks menu, jawaban, dan pesan standar sesuai Dokumen Skenario FAQ
 * (M-00..M-10, MSG-01..09, ESK-01, PGD-01..10) dan Matriks Eskalasi (A1..C6).
 */
final class BotScript
{
    public const FOOTER = '9 = Kembali | 99 = Menu Utama';

    /**
     * Menu: judul, jalur, induk, pilihan (angka => [label, tujuan]).
     * Tujuan: kode menu (M-xx), "ANS:<jalur>", "PGD-01", atau "ESK".
     */
    public const MENUS = [
        'M-00' => ['title' => null, 'path' => '', 'parent' => null, 'options' => [
            '0' => ['Warga Negara Indonesia (WNI)', 'M-01'],
            '1' => ['Warga Negara Asing (WNA)', 'M-08'],
            '2' => ['Pengaduan', 'PGD-01'],
            '3' => ['Hubungi Petugas', 'ESK'],
        ]],
        'M-01' => ['title' => 'Menu WNI', 'path' => '0', 'parent' => 'M-00', 'options' => [
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
        'M-08' => ['title' => 'Menu WNA', 'path' => '1', 'parent' => 'M-00', 'options' => [
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

    public static function mainMenu(bool $aiHint = false): string
    {
        $text = "Selamat datang di layanan WhatsApp *Kantor Imigrasi Kelas I TPI Cilacap*.\n"
            ."Silakan pilih layanan dengan mengetik angka:\n"
            .self::optionLines('M-00')
            ."\n\nKetik *88* kapan saja untuk menghubungi petugas.";

        if ($aiHint) {
            $text .= "\nAnda juga dapat langsung mengetik pertanyaan.";
        }

        return $text;
    }

    public static function menu(string $code, ?string $intro = null): string
    {
        $menu = self::MENUS[$code];
        $text = '*'.$menu['title']."*\n";

        if ($intro) {
            $text .= $intro."\n\n";
        }

        return $text."Silakan pilih dengan mengetik angka:\n".self::optionLines($code)."\n\n".self::FOOTER;
    }

    public static function optionLines(string $code): string
    {
        return collect(self::MENUS[$code]['options'])
            ->map(fn ($option, $key) => "*{$key}* = {$option[0]}")
            ->implode("\n");
    }

    public static function esk01(): string
    {
        $contact = config('kancil.contact');

        return "*Hubungi Petugas*\nPertanyaan Anda belum terjawab di menu? Silakan hubungi:\n"
            ."• Call center / WhatsApp: *{$contact['whatsapp']}*\n"
            ."• Email: *{$contact['email']}*";
    }

    public static function msg(string $id, ?string $fill = null): string
    {
        $contact = config('kancil.contact');

        return match ($id) {
            'MSG-01' => 'Maaf, pilihan tidak valid. Silakan ketik angka sesuai daftar menu.',
            'MSG-02' => 'Maaf, pilihan masih belum sesuai. Bot ini hanya menerima angka pilihan seperti pada menu.',
            'MSG-03' => 'Mohon maaf, bot belum dapat memahami pesan Anda. Ketik *88* untuk menghubungi petugas atau *99* untuk menu utama.',
            'MSG-04' => 'Maaf, bot hanya menerima pesan teks berupa angka pilihan. Silakan ketik angka sesuai menu.',
            'MSG-05' => "Informasi ini sedang diperbarui. Untuk kepastian, silakan hubungi petugas di call center/WhatsApp *{$contact['whatsapp']}* atau email *{$contact['email']}*.",
            'MSG-06' => 'Sesi sebelumnya telah berakhir. Silakan pilih layanan kembali:',
            'MSG-07' => 'Saat ini di luar jam layanan petugas.'.($fill ? " Silakan hubungi kembali pada jam layanan: {$fill}." : ' Silakan hubungi kembali pada jam layanan.'),
            'MSG-08' => 'Pengaduan dibatalkan. Anda dapat memulai kembali kapan saja.',
            'MSG-09' => 'Sesi sebelumnya telah berakhir, dan pengaduan yang belum dikirim tidak tersimpan. Pilih *2* (Pengaduan) untuk memulai lagi.',
            'OFFER-88' => 'Ketik *88* untuk menghubungi petugas.',
            'AI-FOOTER' => 'Ketik *99* untuk menu utama atau *88* untuk menghubungi petugas.',
            'AI-UNKNOWN' => 'Mohon maaf, pertanyaan Anda belum dapat dijawab otomatis.',
            'AI-DISCLAIMER' => '_Informasi umum. Untuk kepastian, silakan hubungi petugas._',
            default => throw new \InvalidArgumentException("Pesan {$id} tidak dikenal."),
        };
    }

    public static function pgd(string $id, array $data = []): string
    {
        return match ($id) {
            'PGD-01' => "Silakan pilih:\n*1* = Buat pengaduan baru\n*2* = Cek status pengaduan\n\n".self::FOOTER,
            'PGD-02' => "Silakan ketik nama lengkap Anda.\n_(Ketik batal untuk membatalkan pengaduan.)_",
            'PGD-03' => "Pilih kategori pengaduan:\n".collect(self::COMPLAINT_CATEGORIES)
                ->map(fn ($c, $k) => "*{$k}* = {$c[0]}")->implode("\n"),
            'PGD-04' => 'Silakan ketik isi pengaduan Anda (kronologi singkat).',
            'PGD-04-SHORT' => 'Isi pengaduan terlalu singkat (minimal '.config('kancil.complaint.min_length').' karakter). Silakan ketik ulang isi pengaduan Anda dengan lebih lengkap.',
            'PGD-05' => "Apakah Anda ingin melampirkan foto atau dokumen?\n*1* = Ya\n*2* = Tidak",
            'PGD-06' => 'Silakan kirim foto atau dokumen (maksimal '.config('kancil.complaint.max_attachments').' berkas). Ketik *selesai* jika sudah.',
            'PGD-06-ACK' => "Berkas ke-{$data['n']} diterima. Kirim berkas lain atau ketik *selesai* jika sudah.",
            'PGD-06-FULL' => 'Batas '.config('kancil.complaint.max_attachments').' berkas sudah tercapai. Ketik *selesai* untuk melanjutkan.',
            'PGD-07' => "*Ringkasan pengaduan Anda*\nNama: {$data['name']}\nKategori: {$data['category']}\nIsi: {$data['text']}\nLampiran: "
                .($data['attachments'] > 0 ? "{$data['attachments']} berkas" : 'tidak ada')
                ."\n\n*1* = Kirim\n*2* = Batalkan",
            'PGD-08' => "Pengaduan Anda sudah diterima. Nomor tiket: *{$data['ticket']}*.\n_Status awal tiket: Baru._\n"
                ."Cek status kapan saja lewat menu *2* (Pengaduan) lalu *2* (Cek status).\n\n99 = Menu Utama",
            'PGD-09' => 'Silakan ketik nomor tiket Anda.',
            'PGD-10' => "Status pengaduan *{$data['ticket']}*: *{$data['status']}*."
                .($data['response'] ? "\nTanggapan petugas: {$data['response']}" : '')
                ."\n\n".self::FOOTER,
            'PGD-10-NOTFOUND' => "Nomor tiket tidak ditemukan untuk nomor WhatsApp ini. Pastikan nomor tiket benar (contoh: ADU-202611-0001).\n\n".self::FOOTER,
            default => throw new \InvalidArgumentException("Pesan {$id} tidak dikenal."),
        };
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
