
document.addEventListener('DOMContentLoaded', function () {
    let dataPengaduan = [
        {
            id: 1,
            tiket: '#PGD-2025-014',
            nama: 'Hendro Wibowo',
            nomor: '+62 813-8821-4920',
            kategori: 'Biaya Denda Paspor',
            tanggal: 'Hari ini, 10:42',
            isi: 'Pemohon menanyakan kepastian prosedur BAP dan rincian denda paspor rusak akibat genangan air banjir, serta konfirmasi jadwal wawancara di loket 3.',
            status: 'Menunggu'
        },
        {
            id: 2,
            tiket: '#PGD-2025-013',
            nama: 'Maria Susanti',
            nomor: '+62 812-4455-6677',
            kategori: 'Kuota M-Paspor',
            tanggal: 'Kemarin, 14:20',
            isi: 'Menanyakan penambahan kuota antrean M-Paspor untuk keberangkatan mendesak.',
            status: 'Diproses'
        },
        {
            id: 3,
            tiket: '#PGD-2025-012',
            nama: 'Ahmad Fauzi',
            nomor: '+62 858-9900-1122',
            kategori: 'Informasi Izin Tinggal',
            tanggal: '12 Feb 2025, 09:15',
            isi: 'Konfirmasi perpanjangan ITAS tenaga kerja asing dan pengambilan paspor di kantor imigrasi.',
            status: 'Selesai'
        },
        {
            id: 4,
            tiket: '#PGD-2025-011',
            nama: 'Dewi Lestari',
            nomor: '+62 877-3322-1100',
            kategori: 'Berkas Paspor Anak',
            tanggal: '11 Feb 2025, 11:05',
            isi: 'Konsultasi kelengkapan berkas paspor anak dengan orang tua berstatus WNA.',
            status: 'Selesai'
        }
    ];

    let pengaduanTerpilih = 1;

    const tableBody = document.getElementById('pengaduanTableBody');
    const searchInput = document.getElementById('pengaduanSearch');
    const statusFilter = document.getElementById('pengaduanStatusFilter');
    const kategoriFilter = document.getElementById('pengaduanKategoriFilter');
    const btnFilter = document.getElementById('btnFilterPengaduan');

    function renderPengaduan() {
        const keyword = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const filterStatus = statusFilter ? statusFilter.value : '';
        const filterKategori = kategoriFilter ? kategoriFilter.value : '';

        const hasil = dataPengaduan.filter(function (item) {
            const cocokPencarian =
                item.nama.toLowerCase().includes(keyword) ||
                item.nomor.toLowerCase().includes(keyword) ||
                item.tiket.toLowerCase().includes(keyword) ||
                item.isi.toLowerCase().includes(keyword);

            const cocokStatus = filterStatus === '' || item.status === filterStatus;
            const cocokKategori = filterKategori === '' || item.kategori === filterKategori;

            return cocokPencarian && cocokStatus && cocokKategori;
        });

        tableBody.innerHTML = '';

        hasil.forEach(function (item) {
            const row = document.createElement('tr');
            row.className = 'pengaduan-row' + (item.id === pengaduanTerpilih ? ' active-row' : '');

            // Tiket
            const colTiket = document.createElement('td');
            colTiket.className = 'col-ticket-code';
            colTiket.textContent = item.tiket;

            // Pemohon (Nama + WhatsApp)
            const colPemohon = document.createElement('td');
            colPemohon.innerHTML = `
                <div class="pemohon-cell">
                    <strong>${item.nama}</strong>
                    <span class="cell-subtext">${item.nomor}</span>
                </div>
            `;

            // Kategori
            const colKategori = document.createElement('td');
            colKategori.textContent = item.kategori;

            // Tanggal
            const colTanggal = document.createElement('td');
            colTanggal.className = 'text-muted';
            colTanggal.textContent = item.tanggal;

            // Status
            const colStatus = document.createElement('td');
            const badgeClass = item.status === 'Menunggu' ? 'waiting' : (item.status === 'Diproses' ? 'agent' : 'success');
            colStatus.innerHTML = `<span class="badge-status-pill ${badgeClass}">${item.status}</span>`;

            // Aksi
            const colAksi = document.createElement('td');
            colAksi.style.textAlign = 'right';
            const btnDetail = document.createElement('button');
            btnDetail.type = 'button';
            btnDetail.className = 'btn-table-action';
            btnDetail.textContent = 'Detail';
            btnDetail.addEventListener('click', function (e) {
                e.stopPropagation();
                pilihPengaduan(item.id);
            });
            colAksi.appendChild(btnDetail);

            row.addEventListener('click', function () {
                pilihPengaduan(item.id);
            });

            row.append(colTiket, colPemohon, colKategori, colTanggal, colStatus, colAksi);
            tableBody.appendChild(row);
        });

        const emptyEl = document.getElementById('pengaduanEmpty');
        if (emptyEl) {
            emptyEl.style.display = hasil.length === 0 ? 'block' : 'none';
        }

        const paginationInfo = document.getElementById('pengaduanPaginationInfo');
        if (paginationInfo) {
            paginationInfo.textContent = `Menampilkan ${hasil.length} dari 100 tiket`;
        }

        perbaruiRingkasan();
    }

    function perbaruiRingkasan() {
        const totalEl = document.getElementById('totalPengaduan');
        const menungguEl = document.getElementById('totalMenunggu');
        const diprosesEl = document.getElementById('totalDiproses');
        const selesaiEl = document.getElementById('totalSelesai');

        if (totalEl) totalEl.textContent = '100';
        if (menungguEl) menungguEl.textContent = '12';
        if (diprosesEl) diprosesEl.textContent = '18';
        if (selesaiEl) selesaiEl.textContent = '70';
    }

    function pilihPengaduan(id) {
        const item = dataPengaduan.find(data => data.id === id);
        if (!item) return;

        pengaduanTerpilih = id;

        const tiketEl = document.getElementById('detailTiket');
        const namaEl = document.getElementById('detailNama');
        const nomorEl = document.getElementById('detailNomor');
        const isiEl = document.getElementById('detailIsi');
        const statusBadge = document.getElementById('detailStatusBadge');
        const statusSelect = document.getElementById('ubahStatusPengaduan');

        if (tiketEl) tiketEl.textContent = item.tiket;
        if (namaEl) namaEl.textContent = item.nama;
        if (nomorEl) nomorEl.textContent = item.nomor;
        if (isiEl) isiEl.textContent = `“${item.isi}”`;
        if (statusBadge) {
            statusBadge.textContent = item.status;
            statusBadge.className = 'badge-status-pill ' + (item.status === 'Menunggu' ? 'waiting' : (item.status === 'Diproses' ? 'agent' : 'success'));
        }
        if (statusSelect) {
            statusSelect.value = item.status;
        }

        // Highlight selected row
        const rows = document.querySelectorAll('.pengaduan-row');
        rows.forEach(r => r.classList.remove('active-row'));
        const activeRow = Array.from(rows).find(r => r.querySelector('.col-ticket-code')?.textContent === item.tiket);
        if (activeRow) activeRow.classList.add('active-row');
    }

    const btnSimpan = document.getElementById('simpanStatusPengaduan');
    if (btnSimpan) {
        btnSimpan.addEventListener('click', function () {
            const item = dataPengaduan.find(data => data.id === pengaduanTerpilih);
            if (!item) return;

            const newStatus = document.getElementById('ubahStatusPengaduan').value;
            item.status = newStatus;

            const tanggapanInput = document.getElementById('tanggapanPetugas');
            if (tanggapanInput && tanggapanInput.value.trim() !== '') {
                tanggapanInput.value = '';
            }

            renderPengaduan();
            pilihPengaduan(item.id);

            alert(`Tanggapan untuk tiket ${item.tiket} berhasil disimpan dengan status "${newStatus}".`);
        });
    }

    if (searchInput) searchInput.addEventListener('input', renderPengaduan);
    if (statusFilter) statusFilter.addEventListener('change', renderPengaduan);
    if (kategoriFilter) kategoriFilter.addEventListener('change', renderPengaduan);
    if (btnFilter) btnFilter.addEventListener('click', renderPengaduan);

    renderPengaduan();
    pilihPengaduan(1);
});
