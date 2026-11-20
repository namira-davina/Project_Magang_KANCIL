<?php

// Sumber: Dokumen Inventarisasi FAQ 3.1 (30 Okt 2026), 61 FAQ; dikalibrasi ulang 20 Nov 2026 (audit).
// Isi wajib sama dengan 3.1 (dikunci tests/Unit/FaqInventoryContractTest). Deviasi terdokumentasi: PSP-011/TRF-003
// ditambah info SIPANTAS (feedback user 2); PSP-013..015 = jawaban menu Bogor di luar 3.1 (perlu verifikasi).
// Data awal untuk FaqSeeder / kancil:faq-sync; setelah seeding, FAQ dikelola lewat dashboard.

return [
    [
        'code' => 'PSP-001',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa saja persyaratan untuk membuat paspor baru?',
        'answer' => 'KTP elektronik, Kartu Keluarga, dan salah satu dari akta lahir/ijazah/buku nikah. Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-002',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa saja persyaratan untuk penggantian paspor?',
        'answer' => 'Untuk paspor terbitan tahun 2009 ke atas dan paspor terbitan luar negeri, cukup melampirkan KTP elektronik dan paspor lama.',
        'menu_path' => '0>1>2',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-003',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor untuk keperluan wisata?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama (jika sudah pernah memiliki paspor)
• Rencana perjalanan (akomodasi, tempat yang dikunjungi, dan lama berwisata).
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>3>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-004',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor untuk keperluan belajar?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama (jika sudah pernah memiliki paspor)
• Surat pengantar dari sekolah/kampus.
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>3>2',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-005',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor untuk berobat ke luar negeri?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama (jika sudah pernah memiliki paspor)
• Surat riwayat kesehatan dan surat rujukan rumah sakit untuk berobat ke luar negeri.
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>3>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-006',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor untuk calon pekerja migran Indonesia (CPMI)?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama (jika sudah pernah memiliki paspor)
• ID CPMI dari BP2MI
• Surat pengantar dari LPK
• Kontrak kerja.
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>3>4',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-007',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor untuk pelaut?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama (jika sudah pernah memiliki paspor)
• BST (Basic Safety Training)
• Buku pelaut
• Surat penempatan dari perusahaan atau surat pengantar dari sekolah pelayaran.
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>3>5',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-008',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor untuk umrah?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama (jika sudah pernah memiliki paspor)
• Surat pengantar dari biro umrah
• Surat pernyataan penambahan nama bermeterai (bagi yang namanya hanya satu kata).
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>3>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-009',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor untuk haji?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama (jika sudah pernah memiliki paspor)
• Surat rekomendasi dari Kementerian Agama
• Bukti pendaftaran ibadah haji (BPIH)
• Surat pernyataan penambahan nama bermeterai (bagi yang namanya hanya satu kata).
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>3>7',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-010',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor untuk magang?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama (jika sudah pernah memiliki paspor)
• Surat pengantar dari LPK
• Kontrak kerja
• Sertifikat kelulusan bahasa.
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>3>8',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-011',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat membuat paspor yang hilang?',
        'answer' => '*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Surat keterangan kehilangan dari kepolisian setempat
• Fotokopi paspor lama (dapat diminta ke kantor imigrasi yang menerbitkan).
Bawa berkas asli dan fotokopi ukuran A4. Denda paspor hilang Rp1.000.000.

Berita Acara Pemeriksaan (BAP) diajukan online melalui https://sipantas.kanimcilacap.com/. Pemohon wajib datang ke kantor imigrasi setelah permohonan terverifikasi oleh petugas.',
        'menu_path' => '0>1>5',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-012',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat permohonan affidavit untuk anak berkewarganegaraan ganda?',
        'answer' => '*Persyaratan:*
• Surat permohonan dan jaminan
• Akta kelahiran anak
• Bukti lapor kelahiran dari Disdukcapil (jika lahir di luar negeri)
• KTP dan Kartu Keluarga orang tua
• Buku nikah orang tua
• Bukti lapor pernikahan dari Disdukcapil (jika menikah di luar negeri)
• Paspor orang tua
• Paspor Indonesia anak (jika ada)
• Paspor asing anak (jika ada)
• Sertifikat ABG.
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-001',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Apa saja persyaratan pembuatan paspor untuk anak?',
        'answer' => '*Persyaratan:*
• KTP elektronik orang tua
• Kartu Keluarga
• Buku nikah orang tua
• Akta kelahiran anak
• Paspor orang tua
• Paspor lama anak (jika ada)
• Akta cerai dan penetapan hak asuh (jika orang tua bercerai)
• Affidavit (untuk anak berkewarganegaraan ganda)
• Surat pernyataan jaminan orang tua bermeterai (formulir tersedia di kantor imigrasi).
Bawa berkas asli dan fotokopi ukuran A4.',
        'menu_path' => '0>1>4>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-002',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Apakah anak di bawah 5 tahun harus mendaftar melalui M-Paspor?',
        'answer' => 'Tidak. Anak di bawah 5 tahun dapat langsung datang ke kantor imigrasi tanpa mendaftar di M-Paspor.',
        'menu_path' => '0>1>4>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-003',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Apakah anak usia 5–17 tahun harus mendaftar melalui M-Paspor?',
        'answer' => 'Ya. Anak usia 5–17 tahun wajib mendaftar melalui aplikasi M-Paspor.',
        'menu_path' => '0>1>4>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-004',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Berapa masa berlaku paspor anak di bawah 17 tahun?',
        'answer' => 'Masa berlaku paspor anak di bawah 17 tahun adalah 5 tahun.',
        'menu_path' => '0>1>4>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-005',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Siapa yang menandatangani surat pernyataan jika kedua orang tua bercerai hidup?',
        'answer' => 'Jika ada penetapan pengadilan tentang hak asuh: orang tua pemegang hak asuh. Jika permohonan diajukan orang tua yang tidak memegang hak asuh, atau perceraian diputus tanpa penetapan hak asuh: kedua orang tua. Jika salah satu orang tua tidak diketahui keberadaannya: orang tua yang diketahui keberadaannya, disertai keterangan bahwa salah satu orang tua tidak ditemukan.',
        'menu_path' => '0>1>4>2',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-006',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Bagaimana jika salah satu orang tua anak sudah meninggal?',
        'answer' => 'Surat pernyataan dibuat oleh orang tua yang masih hidup, dengan melampirkan surat kematian orang tua yang meninggal.',
        'menu_path' => '0>1>4>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-007',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Bagaimana jika kedua orang tua anak sudah meninggal?',
        'answer' => 'Surat pernyataan dibuat oleh keluarga sedarah dalam garis lurus ke atas berdasarkan penetapan pengadilan tentang perwalian anak, dengan melampirkan surat kematian kedua orang tua.',
        'menu_path' => '0>1>4>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-008',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Bagaimana jika anak yatim piatu berada di panti asuhan atau dipelihara negara?',
        'answer' => 'Surat pernyataan dibuat oleh yayasan atau dinas sosial.',
        'menu_path' => '0>1>4>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'ANK-009',
        'group' => 'Paspor Anak dan Kondisi Keluarga',
        'question' => 'Bagaimana persyaratan paspor untuk anak yang diadopsi?',
        'answer' => 'Surat pernyataan dibuat oleh orang tua asuh berdasarkan penetapan pengadilan.',
        'menu_path' => '0>1>4>4',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-001',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Apa syarat mengambil paspor sendiri?',
        'answer' => '• KTP elektronik
• Bukti permohonan
• Bukti pembayaran.',
        'menu_path' => '0>3>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-002',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Apa syarat mengambil paspor yang diwakilkan anggota keluarga dalam satu Kartu Keluarga?',
        'answer' => '• KTP elektronik, bukti permohonan, dan bukti pembayaran
• Kartu Keluarga (fotokopi dan menunjukkan aslinya)
• KTP pengambil (fotokopi dan menunjukkan aslinya).',
        'menu_path' => '0>3>2',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-003',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Apa syarat mengambil paspor yang diwakilkan orang di luar Kartu Keluarga?',
        'answer' => '• KTP elektronik, bukti permohonan, dan bukti pembayaran
• Surat kuasa bermeterai dari pemilik paspor
• KTP pengambil (fotokopi dan menunjukkan aslinya).',
        'menu_path' => '0>3>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-004',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Berapa hari kerja sampai paspor diambilkan pada layanan pengiriman melalui pos?',
        'answer' => 'Pada layanan pengiriman paspor melalui pos, paspor diambilkan setelah 4 hari kerja sejak proses permohonan selesai.
Lama penyelesaian untuk pengambilan biasa tidak tercantum; silakan hubungi petugas.',
        'menu_path' => '0>7',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-005',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Bagaimana ketentuan pengiriman paspor melalui pos di Kantor Imigrasi Cilacap?',
        'answer' => '• Pemohon datang sendiri ke Koperasi dan sudah menyelesaikan seluruh tahapan permohonan paspor.
• Mengisi formulir di Koperasi Pintu Gerbang Sejahtera dan menandatangani surat kuasa bermeterai.
• Setelah paspor diambilkan, paspor difoto dan dikirim ke HP pemohon untuk konfirmasi kebenaran data.
• Paspor dijemput Pos Indonesia dan dikirim ke alamat sesuai permintaan, mengikuti standar pengiriman Pos Indonesia.
Syarat dan ketentuan lain berlaku.',
        'menu_path' => '0>7',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-006',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Apakah pengurusan pengiriman paspor melalui pos dapat diwakilkan?',
        'answer' => 'Tidak. Pemohon datang sendiri ke koperasi, dan surat kuasa bermeterai harus ditandatangani sendiri (tidak boleh diwakilkan).',
        'menu_path' => '0>7',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-007',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Dokumen apa yang disiapkan untuk surat kuasa pengambilan paspor pada layanan pos?',
        'answer' => '• Fotokopi KTP
• Fotokopi KK (jika ada anggota keluarga yang memproses paspor bersamaan)
• Bukti permohonan paspor (M-Paspor) atau resi permohonan
• Bukti pembayaran paspor (bukti transfer/struk).',
        'menu_path' => '0>7',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-008',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Apakah penerima kuasa bertanggung jawab jika persyaratan masih kurang atau paspor belum jadi?',
        'answer' => 'Tidak. Penerima kuasa tidak bertanggung jawab bila di kemudian hari saat pengambilan ternyata masih ada kekurangan syarat permohonan dan/atau paspor belum selesai.',
        'menu_path' => '0>7',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'AMB-009',
        'group' => 'Pengambilan, Pengiriman, dan Status Paspor',
        'question' => 'Bagaimana cara mengecek status permohonan paspor?',
        'answer' => 'Hubungi call center *081217000900* dengan format: *CEK#Nomor Permohonan#Nama#Lokasi Pembuatan*
Pilihan lokasi: Kanim Cilacap / MPP Banyumas / MPP Purbalingga / Kemenag Purbalingga.',
        'menu_path' => '0>4',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'BYR-001',
        'group' => 'Pembayaran Paspor',
        'question' => 'Bagaimana cara membayar paspor melalui ATM?',
        'answer' => 'Siapkan kode billing, lalu:
• *BCA:* Pembayaran › MPN/Pajak › Penerimaan Negara › masukkan kode MPN G2.
• *BNI:* Menu Lainnya › Pembayaran › Penerimaan Negara › Pajak/PNBP › masukkan kode MPN.
• *BRI:* Transaksi Lainnya › Pembayaran › Lainnya › MPN › masukkan kode MPN G2.
• *Mandiri:* Bayar/Beli › Penerimaan Negara › Pajak/PNBP/Cukai › masukkan kode MPN › Konfirmasi Pembayaran.
Simpan bukti pembayaran.',
        'menu_path' => '0>2>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'BYR-002',
        'group' => 'Pembayaran Paspor',
        'question' => 'Apakah pembayaran paspor bisa dilakukan melalui mobile banking atau internet banking?',
        'answer' => 'Bisa. Siapkan kode billing, lalu:
• *BNI Mobile:* Pembayaran › MPN G2 › masukkan nomor MPN di kolom nomor tagihan › konfirmasi dengan PIN transaksi.
• *KlikBCA* (ibank.klikbca.com): Pembayaran › Pajak › Penerimaan Negara › masukkan nomor MPN di kolom kode billing › konfirmasi dengan KeyBCA.
• *BRImo:* Tagihan › Penerimaan Negara › masukkan nomor MPN G2 › konfirmasi dengan password dan M-Token.
• *Livin’ by Mandiri:* Bayar › Pembayaran Baru › Pembayaran Negara › pilih IDR/Pajak/PNBP/Cukai pada kolom penyedia jasa.',
        'menu_path' => '0>2>2',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'BYR-003',
        'group' => 'Pembayaran Paspor',
        'question' => 'Apakah pembayaran paspor bisa dilakukan melalui aplikasi dompet digital atau marketplace?',
        'answer' => 'Bisa. Siapkan kode billing, lalu:
• *DANA:* Lihat Semua › Penerimaan Negara › Penerimaan Negara Bukan Pajak › masukkan kode billing › simpan resi.
• *Tokopedia:* Top-Up dan Tagihan › Penerimaan Negara › Bayar PNBP › masukkan kode bayar MPN G2.
• *GoPay:* Semua Menu › Layanan Publik › Penerimaan Negara › Penerimaan Negara Bukan Pajak › masukkan kode bayar.
• *Shopee:* Semua Menu › Pulsa, Tagihan & Tiket › Penerimaan Negara › Penerimaan Negara Bukan Pajak › masukkan kode bayar.',
        'menu_path' => '0>2>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'BYR-004',
        'group' => 'Pembayaran Paspor',
        'question' => 'Apakah pembayaran paspor bisa dilakukan di kantor pos, Indomaret, atau Alfamart?',
        'answer' => 'Bisa. Datang ke kantor Pos Indonesia, Indomaret, atau Alfamart terdekat, berikan nomor kode billing kepada petugas/kasir, lakukan pembayaran, lalu simpan resi pembayaran.',
        'menu_path' => '0>2>4',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-001',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa tarif paspor biasa elektronik dengan masa berlaku 5 tahun?',
        'answer' => 'Rp650.000 (paspor biasa elektronik laminasi, masa berlaku 5 tahun).',
        'menu_path' => '0>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-002',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa tarif paspor biasa elektronik dengan masa berlaku 10 tahun?',
        'answer' => 'Rp950.000 (paspor biasa elektronik laminasi, masa berlaku 10 tahun).',
        'menu_path' => '0>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-003',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa denda paspor hilang dan paspor rusak?',
        'answer' => '• Denda paspor hilang: Rp1.000.000
• Denda paspor rusak: Rp500.000.
Berita Acara Pemeriksaan (BAP) paspor hilang, rusak, dan penambahan nama dapat diajukan online melalui https://sipantas.kanimcilacap.com/.',
        'menu_path' => '0>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-004',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa biaya layanan percepatan paspor?',
        'answer' => 'Biaya layanan percepatan adalah Rp1.000.000.',
        'menu_path' => '0>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-005',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa tarif izin masuk kembali (Re-entry Permit)?',
        'answer' => '• Sampai 30 hari: Rp300.000
• Sampai 60 hari: Rp400.000
• Sampai 90 hari: Rp500.000
• Sampai 6 bulan: Rp750.000
• Sampai 1 tahun: Rp1.500.000
• Sampai 2 tahun: Rp2.000.000
• Sampai 5 tahun: Rp3.500.000
• Sampai 10 tahun: Rp5.000.000
• Masa berlaku tidak terbatas: Rp8.000.000.',
        'menu_path' => '1>1>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-006',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa tarif Izin Tinggal Kunjungan (ITK)?',
        'answer' => '• Sampai 7 hari: Rp250.000
• Sampai 14 hari: Rp350.000
• Sampai 30 hari: Rp500.000
• Sampai 60 hari: Rp1.000.000
• Sampai 90 hari: Rp1.500.000
• Sampai 180 hari: Rp2.000.000.',
        'menu_path' => '1>1>2',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-007',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa tarif Izin Tinggal Terbatas (ITAS)?',
        'answer' => '• Sampai 30 hari: Rp500.000
• Sampai 60 hari: Rp1.000.000
• Sampai 90 hari: Rp1.500.000
• Sampai 6 bulan: Rp2.000.000
• Sampai 1 tahun: Rp3.000.000
• Sampai 2 tahun: Rp5.000.000
• Sampai 5 tahun: Rp7.000.000
• Sampai 10 tahun: Rp12.000.000.',
        'menu_path' => '1>1>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-008',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa tarif Izin Tinggal Tetap (ITAP)?',
        'answer' => '• Sampai 5 tahun: Rp7.000.000
• Sampai 10 tahun: Rp12.000.000
• Jangka waktu tidak terbatas: Rp15.000.000.',
        'menu_path' => '1>1>4',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-009',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa tarif izin meninggalkan Indonesia untuk tidak kembali (Exit Permit Only)?',
        'answer' => 'Rp100.000 per orang.',
        'menu_path' => '1>1>5',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-010',
        'group' => 'Tarif PNBP',
        'question' => 'Berapa tarif visa kunjungan beberapa kali perjalanan?',
        'answer' => '• Sampai 60 hari: Rp1.500.000
• Sampai 90 hari: Rp2.000.000
• Sampai 180 hari: Rp2.500.000
• Sampai 1 tahun: Rp3.000.000
• Sampai 2 tahun: Rp5.000.000
• Sampai 5 tahun: Rp10.000.000
• Sampai 10 tahun: Rp15.000.000.',
        'menu_path' => '1>1>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'TRF-011',
        'group' => 'Tarif PNBP',
        'question' => 'Apa dasar hukum tarif tersebut?',
        'answer' => 'Tarif mengacu pada Peraturan Pemerintah (PP) Nomor 45 Tahun 2024 tentang Jenis dan Tarif atas Jenis Penerimaan Negara Bukan Pajak yang berlaku pada Kementerian Hukum dan Hak Asasi Manusia.',
        'menu_path' => '0>6 dan 1>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-001',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Apa saja layanan khusus di Kantor Imigrasi Cilacap?',
        'answer' => '• KANCIL NARI (layanan prioritas)
• KANCIL PERSAMI (pengambilan paspor Sabtu–Minggu)
• KANCIL PESAT (paspor darurat bagi pemohon sakit)
• Eazy Paspor (layanan jemput bola untuk kelompok)
• KANCIL NGAPAK (layanan paspor di kabupaten)
• HOTS (Haji On The Spot).',
        'menu_path' => '0>5',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-002',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Apa itu layanan KANCIL NARI?',
        'answer' => 'Layanan prioritas bagi ibu hamil, penyandang disabilitas, anak di bawah 5 tahun, dan lansia di atas 60 tahun untuk mengajukan paspor langsung di Kantor Imigrasi Cilacap. Penerima layanan mendapat antrean khusus berkode C-(nomor urut) dan dapat membuat paspor tanpa mendaftar di M-Paspor.',
        'menu_path' => '0>5>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-003',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Apa itu layanan KANCIL PERSAMI?',
        'answer' => 'Layanan pengambilan paspor pada hari Sabtu dan Minggu, pukul 08.00–12.00, bagi pemohon yang sibuk pada hari kerja.',
        'menu_path' => '0>5>2',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-004',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Apa itu layanan KANCIL PESAT?',
        'answer' => 'Layanan paspor darurat bagi pemohon yang sakit dan tidak bisa datang ke Kantor Imigrasi Cilacap. Petugas datang ke rumah atau tempat lain untuk mengambil data biometrik dan wawancara.',
        'menu_path' => '0>5>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-005',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Siapa yang dapat mengajukan layanan KANCIL PESAT?',
        'answer' => '• WNI yang bertempat atau dirawat di wilayah kerja Kantor Imigrasi Cilacap
• Sakit atau berpenyakit sehingga tidak dapat datang langsung, atau bergantung pada peralatan penunjang medis
• Memiliki surat keterangan sakit (diagnosa) dari dokter rumah sakit
• Memenuhi persyaratan permohonan paspor yang berlaku.',
        'menu_path' => '0>5>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-006',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Apa itu layanan Eazy Paspor?',
        'answer' => '• Layanan jemput bola: petugas datang ke lokasi pemohon (“Kamu di situ aja, kami yang datang ke sana”). Pengajuan minimal 30 pemohon ke Kantor Imigrasi Kelas I TPI Cilacap, lewat email atau WhatsApp
• Hanya untuk pembuatan paspor baru dan penggantian paspor
• Jadwal ditentukan kantor imigrasi pada hari kerja.',
        'menu_path' => '0>5>4',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-007',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Siapa yang dapat mengajukan Eazy Paspor?',
        'answer' => 'Perkantoran/instansi, institusi pendidikan, komunitas/organisasi, serta kompleks perumahan/apartemen.',
        'menu_path' => '0>5>4',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-008',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Bagaimana cara mengajukan Eazy Paspor?',
        'answer' => 'Ajukan lewat WhatsApp *081217000900* atau email *cilacap@imigrasi.go.id*. Pengajuan minimal 30 pemohon dan jadwal ditentukan oleh kantor imigrasi.',
        'menu_path' => '0>5>4',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-009',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Apa itu layanan KANCIL NGAPAK?',
        'answer' => 'KANCIL NGAPAK (Ngelayani Paspor nang Kabupaten) memudahkan pemohon mengajukan paspor pada hari Sabtu dan Minggu tanpa harus ke kantor imigrasi, di MPP Banyumas dan MPP Purbalingga.',
        'menu_path' => '0>5>5',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-010',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Apa itu layanan HOTS (Haji On The Spot)?',
        'answer' => 'Layanan jemput bola bagi calon jemaah haji untuk membuat atau memperpanjang paspor langsung di lokasi, tanpa antre di kantor imigrasi. Wilayah layanan: Kabupaten Cilacap, Banyumas, Purbalingga, dan Banjarnegara.',
        'menu_path' => '0>5>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'LYK-011',
        'group' => 'Layanan Khusus Kantor Imigrasi Cilacap',
        'question' => 'Apa persyaratan umum layanan HOTS?',
        'answer' => '• KTP dan Kartu Keluarga
• Akta lahir / buku nikah / ijazah
• Paspor lama (jika ada)
• Surat rekomendasi dari Kementerian Agama
• BPIH.',
        'menu_path' => '0>5>6',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'WNA-001',
        'group' => 'Layanan WNA – Pelaporan WNA Meninggal di Indonesia',
        'question' => 'Jika WNA meninggal dunia di Indonesia, ke mana harus melapor?',
        'answer' => 'Laporkan ke Kantor Imigrasi terdekat dengan membawa dokumen pendukung. Pelaporan wajib paling lambat 60 hari sejak tanggal kematian. Jika lebih dari 60 hari, akan ditindaklanjuti dengan proses pemeriksaan yang dituangkan dalam berita acara pemeriksaan.',
        'menu_path' => '1>2>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'WNA-002',
        'group' => 'Layanan WNA – Pelaporan WNA Meninggal di Indonesia',
        'question' => 'Siapa yang wajib melaporkan kematian WNA?',
        'answer' => 'Penjamin Orang Asing (sponsor) yang tercatat dalam sistem keimigrasian.',
        'menu_path' => '1>2>1',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'WNA-003',
        'group' => 'Layanan WNA – Pelaporan WNA Meninggal di Indonesia',
        'question' => 'Apa saja dokumen untuk melaporkan kematian WNA?',
        'answer' => '• Surat permohonan dari sponsor
• KTP sponsor
• Paspor asli dan fotokopi
• Izin tinggal terakhir (ITAS/ITAP)
• Surat keterangan kematian dari rumah sakit atau pihak berwenang
• Akta kematian dari Dinas Kependudukan dan Pencatatan Sipil.',
        'menu_path' => '1>2>2',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'WNA-004',
        'group' => 'Layanan WNA – Pelaporan WNA Meninggal di Indonesia',
        'question' => 'Bagaimana alur pelaporan kematian WNA?',
        'answer' => '• Penjamin melaporkan ke rumah sakit
• Penjamin melaporkan ke Dinas Kependudukan dan Pencatatan Sipil
• Penjamin melaporkan ke Kantor Imigrasi.',
        'menu_path' => '1>2>3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'KTK-001',
        'group' => 'Kontak',
        'question' => 'Bagaimana cara menghubungi Kantor Imigrasi Cilacap?',
        'answer' => '• Call center / WhatsApp: *081217000900*
• Email: *cilacap@imigrasi.go.id*.',
        'menu_path' => '3',
        'status' => 'perlu_verifikasi',
    ],
    [
        'code' => 'PSP-013',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Bagaimana cara mendaftar permohonan paspor melalui aplikasi M-Paspor?',
        'answer' => 'Unduh aplikasi M-Paspor:
• Android: https://play.google.com/store/apps/details?id=id.go.imigrasi.paspor_online
• iOS: https://apps.apple.com/id/app/m-paspor/id1576336459

*Langkah pendaftaran:*
• Daftar dan login akun
• Lengkapi data diri
• Pilih jenis paspor, tanggal, dan lokasi
• Lakukan pembayaran

Batas waktu pembayaran adalah 120 menit setelah kode billing diterbitkan.
Kesalahan pengisian data dapat menyebabkan permohonan dibatalkan dan biaya tidak dapat dikembalikan.',
        'menu_path' => '1>5 (menu Bogor)',
        'status' => 'perlu_verifikasi',
        'source' => 'Menu Bogor (feedback user 1), di luar Inventarisasi 3.1',
    ],
    [
        'code' => 'PSP-014',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat permohonan paspor yang rusak?',
        'answer' => 'Permohonan paspor rusak tidak melalui aplikasi M-Paspor. Berita Acara Pemeriksaan (BAP) diajukan online melalui https://sipantas.kanimcilacap.com/. Pemohon wajib datang ke kantor imigrasi setelah permohonan terverifikasi oleh petugas.

*Persyaratan:*
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Paspor lama yang rusak
• Meterai Rp10.000
Bawa berkas asli dan fotokopi ukuran A4.

*PNBP (termasuk denda paspor rusak Rp500.000):*
• 5 tahun: Rp1.150.000
• 10 tahun: Rp1.450.000',
        'menu_path' => '1>8 (menu Bogor)',
        'status' => 'perlu_verifikasi',
        'source' => 'Menu Bogor + feedback user 2 (SIPANTAS), di luar Inventarisasi 3.1',
    ],
    [
        'code' => 'PSP-015',
        'group' => 'Permohonan Paspor WNI',
        'question' => 'Apa syarat penambahan nama pada paspor?',
        'answer' => '*Persyaratan:*
• Paspor masih aktif
• KTP elektronik
• Kartu Keluarga
• Akta lahir / ijazah / buku nikah
• Surat rekomendasi travel (umrah) atau BPIH (haji)
• Meterai Rp10.000
Berita Acara Pemeriksaan (BAP) penambahan nama diajukan online melalui https://sipantas.kanimcilacap.com/. Pemohon wajib datang ke kantor imigrasi dengan membawa dokumen asli dan fotokopi A4 utuh.',
        'menu_path' => '1>9 (menu Bogor)',
        'status' => 'perlu_verifikasi',
        'source' => 'Menu Bogor + feedback user 2 (SIPANTAS), di luar Inventarisasi 3.1',
    ],
];
