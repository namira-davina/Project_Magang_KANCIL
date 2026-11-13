document.addEventListener('DOMContentLoaded', function () {
    const config = window.KANCIL_PENGADUAN || { items: [], canUpdate: false, perPage: 10 };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    let dataPengaduan = config.items || [];
    let pengaduanTerpilih = dataPengaduan[0] ? dataPengaduan[0].id : null;
    let halaman = 1;

    const tableBody = document.getElementById('pengaduanTableBody');
    const searchInput = document.getElementById('pengaduanSearch');
    const statusFilter = document.getElementById('pengaduanStatusFilter');
    const kategoriFilter = document.getElementById('pengaduanKategoriFilter');
    const btnFilter = document.getElementById('btnFilterPengaduan');
    const pagination = document.getElementById('pengaduanPagination');

    function badgeClass(status) {
        return status === 'Menunggu' ? 'waiting' : (status === 'Diproses' ? 'agent' : 'success');
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function hasilFilter() {
        const keyword = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const filterStatus = statusFilter ? statusFilter.value : '';
        const filterKategori = kategoriFilter ? kategoriFilter.value : '';

        return dataPengaduan.filter(function (item) {
            const cocokPencarian =
                (item.nama || '').toLowerCase().includes(keyword) ||
                (item.nomor || '').toLowerCase().includes(keyword) ||
                (item.tiket || '').toLowerCase().includes(keyword) ||
                (item.isi || '').toLowerCase().includes(keyword);

            const cocokStatus = filterStatus === '' || item.status === filterStatus;
            const cocokKategori = filterKategori === '' || item.kategori === filterKategori;

            return cocokPencarian && cocokStatus && cocokKategori;
        });
    }

    function renderPengaduan() {
        const hasil = hasilFilter();
        const perPage = config.perPage || 10;
        const totalHalaman = Math.max(1, Math.ceil(hasil.length / perPage));
        halaman = Math.min(halaman, totalHalaman);
        const tampil = hasil.slice((halaman - 1) * perPage, halaman * perPage);

        tableBody.innerHTML = '';

        tampil.forEach(function (item) {
            const row = document.createElement('tr');
            row.className = 'pengaduan-row' + (item.id === pengaduanTerpilih ? ' active-row' : '');
            row.dataset.id = item.id;

            const colTiket = document.createElement('td');
            colTiket.className = 'col-ticket-code';
            colTiket.textContent = item.tiket;

            const colPemohon = document.createElement('td');
            colPemohon.innerHTML = `
                <div class="pemohon-cell">
                    <strong>${escapeHtml(item.nama)}</strong>
                    <span class="cell-subtext">${escapeHtml(item.nomor)}</span>
                </div>
            `;

            const colKategori = document.createElement('td');
            colKategori.textContent = item.kategori;

            const colTanggal = document.createElement('td');
            colTanggal.className = 'text-muted';
            colTanggal.textContent = item.tanggal;

            const colStatus = document.createElement('td');
            colStatus.innerHTML = `<span class="badge-status-pill ${badgeClass(item.status)}">${escapeHtml(item.status)}</span>`
                + (item.terlambat ? ' <span class="badge-status-pill waiting">Terlambat</span>' : '');

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
            paginationInfo.textContent = `Menampilkan ${tampil.length} dari ${hasil.length} tiket`;
        }

        renderPagination(totalHalaman);
        perbaruiRingkasan();
    }

    function renderPagination(totalHalaman) {
        if (!pagination) return;
        pagination.innerHTML = '';

        const tombol = function (label, target, className, disabled, active) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = className + (active ? ' active' : '');
            btn.innerHTML = label;
            btn.disabled = disabled;
            btn.addEventListener('click', function () {
                halaman = target;
                renderPengaduan();
            });
            pagination.appendChild(btn);
        };

        tombol('&lt;', halaman - 1, 'btn-page-nav', halaman <= 1, false);
        for (let i = 1; i <= totalHalaman; i++) {
            if (totalHalaman > 7 && Math.abs(i - halaman) > 2 && i !== 1 && i !== totalHalaman) continue;
            tombol(String(i), i, 'btn-page-num', false, i === halaman);
        }
        tombol('&gt;', halaman + 1, 'btn-page-nav', halaman >= totalHalaman, false);
    }

    function perbaruiRingkasan() {
        const hitung = status => dataPengaduan.filter(item => item.status === status).length;
        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value.toLocaleString('id-ID');
        };

        set('totalPengaduan', dataPengaduan.length);
        set('totalMenunggu', hitung('Menunggu'));
        set('totalDiproses', hitung('Diproses'));
        set('totalSelesai', hitung('Selesai'));
    }

    function pilihPengaduan(id) {
        const item = dataPengaduan.find(data => data.id === id);
        if (!item) return;

        pengaduanTerpilih = id;

        const set = (elId, value) => {
            const el = document.getElementById(elId);
            if (el) el.textContent = value;
        };

        set('detailTiket', item.tiket);
        set('detailNama', item.nama);
        set('detailNomor', item.nomor);
        set('detailIsi', `“${item.isi}”` + (item.lampiran ? ` (${item.lampiran} lampiran)` : ''));
        set('detailPrioritas', (item.prioritas || '-') + (item.level === 2 ? ' • Level 2 (Pimpinan)' : '') + (item.terlambat ? ' • Terlambat' : ''));

        const tanggapanLama = document.getElementById('detailTanggapanLama');
        if (tanggapanLama) {
            tanggapanLama.style.display = item.tanggapan ? 'block' : 'none';
            tanggapanLama.textContent = item.tanggapan ? `Tanggapan terakhir: ${item.tanggapan}` : '';
        }

        const link = document.getElementById('detailPercakapanLink');
        if (link) {
            link.style.display = item.percakapan_url ? 'inline-block' : 'none';
            link.href = item.percakapan_url || '#';
        }

        const statusBadge = document.getElementById('detailStatusBadge');
        if (statusBadge) {
            statusBadge.textContent = item.status;
            statusBadge.className = 'badge-status-pill ' + badgeClass(item.status);
        }

        const statusSelect = document.getElementById('ubahStatusPengaduan');
        if (statusSelect) statusSelect.value = item.status;

        document.querySelectorAll('.pengaduan-row').forEach(function (r) {
            r.classList.toggle('active-row', Number(r.dataset.id) === id);
        });
    }

    const btnSimpan = document.getElementById('simpanStatusPengaduan');
    if (btnSimpan && config.canUpdate) {
        btnSimpan.addEventListener('click', async function () {
            const item = dataPengaduan.find(data => data.id === pengaduanTerpilih);
            if (!item) return;

            const newStatus = document.getElementById('ubahStatusPengaduan').value;
            const tanggapanInput = document.getElementById('tanggapanPetugas');
            const tanggapan = tanggapanInput ? tanggapanInput.value.trim() : '';

            btnSimpan.disabled = true;
            try {
                const response = await fetch(item.update_url, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ status: newStatus, tanggapan: tanggapan }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                    throw new Error(firstError || data.message || 'Gagal menyimpan tanggapan.');
                }

                const index = dataPengaduan.findIndex(d => d.id === item.id);
                dataPengaduan[index] = data.complaint;
                if (tanggapanInput) tanggapanInput.value = '';

                renderPengaduan();
                pilihPengaduan(item.id);

                alert(`Tiket ${item.tiket} disimpan dengan status "${newStatus}".`
                    + (data.notified ? ' Tanggapan dikirim ke WhatsApp pelapor.' : ''));
            } catch (e) {
                alert(e.message);
            } finally {
                btnSimpan.disabled = false;
            }
        });
    }

    const resetHalaman = function () {
        halaman = 1;
        renderPengaduan();
    };

    if (searchInput) searchInput.addEventListener('input', resetHalaman);
    if (statusFilter) statusFilter.addEventListener('change', resetHalaman);
    if (kategoriFilter) kategoriFilter.addEventListener('change', resetHalaman);
    if (btnFilter) btnFilter.addEventListener('click', resetHalaman);

    renderPengaduan();
    if (pengaduanTerpilih) pilihPengaduan(pengaduanTerpilih);
});
