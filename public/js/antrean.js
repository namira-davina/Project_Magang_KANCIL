document.addEventListener('DOMContentLoaded', function () {
    const config = window.KANCIL_ANTREAN || { tickets: [], history: [], stats: {}, urls: {}, canAct: false, pollMs: 8000 };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const body = document.getElementById('antreanTableBody');
    const empty = document.getElementById('antreanEmpty');
    const historyBody = document.getElementById('riwayatTableBody');
    const historyEmpty = document.getElementById('riwayatEmpty');
    const nextButton = document.getElementById('btnAmbilBerikutnya');
    let busy = false;

    async function request(method, target, payload) {
        const response = await fetch(target, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload ? JSON.stringify(payload) : undefined,
            credentials: 'same-origin',
        });

        if (response.status === 401 || response.status === 419) {
            window.location.reload();
            throw new Error('Sesi berakhir');
        }

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
            throw new Error(firstError || data.message || 'Permintaan gagal.');
        }
        return data;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function duration(minutes) {
        if (minutes == null) return '-';
        if (minutes < 60) return `${minutes} mnt`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours} j ${minutes % 60} mnt`;
        return `${Math.floor(hours / 24)} hr ${hours % 24} j`;
    }

    function statusBadge(status) {
        const cls = { 'Menunggu': 'waiting', 'Ditangani': 'agent', 'Selesai': 'success' }[status] || 'bot';
        return `<span class="badge-status-pill ${cls}">${escapeHtml(status)}</span>`;
    }

    function priorityBadge(priority) {
        if (!priority) return '';
        const cls = { 'Tinggi': 'priority-high', 'Sedang': 'priority-medium', 'Rendah': 'priority-low' }[priority] || '';
        return ` <span class="ticket-priority ${cls}">${escapeHtml(priority)}</span>`;
    }

    function actions(t) {
        const open = `<a class="btn-outline btn-sm" href="${escapeHtml(t.percakapan_url)}">Buka Chat</a>`;
        if (!config.canAct) return open;

        const take = t.status === 'Menunggu'
            ? `<button type="button" class="btn-primary-dark btn-sm" data-action="ambil" data-url="${escapeHtml(t.ambil_url)}">Ambil</button>`
            : '';
        const done = `<button type="button" class="btn-outline btn-sm" data-action="selesai" data-url="${escapeHtml(t.selesai_url)}" data-nomor="${escapeHtml(t.nomor)}">Selesaikan</button>`;
        const complaint = `<button type="button" class="btn-outline btn-sm" data-action="pengaduan" data-id="${escapeHtml(t.id)}">Jadikan Pengaduan</button>`;

        return `<div class="ticket-actions">${take}${open}${done}${complaint}</div>`;
    }

    let current = config.tickets || [];

    function render(data) {
        current = data.tickets || [];
        body.innerHTML = '';
        (data.tickets || []).forEach(function (t) {
            const row = document.createElement('tr');
            row.className = t.milik_saya ? 'ticket-row-mine' : '';
            const question = t.pertanyaan ? `<div class="text-secondary-cell">“${escapeHtml(t.pertanyaan)}”</div>` : '';
            const officer = t.petugas ? `<div class="text-secondary-cell">${escapeHtml(t.petugas)}${t.milik_saya ? ' (Anda)' : ''}</div>` : '';
            row.innerHTML = `
                <td class="font-medium">${t.posisi ? escapeHtml(t.posisi) : '-'}</td>
                <td><strong>${escapeHtml(t.nomor)}</strong><div class="text-secondary-cell">No. ${escapeHtml(t.antrean)}${t.kode ? ' • ' + escapeHtml(t.kode) : ''}${priorityBadge(t.prioritas)}</div></td>
                <td>${escapeHtml(t.nama)}<div class="text-secondary-cell">${escapeHtml(t.telepon)}</div></td>
                <td>${escapeHtml(t.topik)}${question}</td>
                <td>${escapeHtml(t.masuk)}<div class="text-secondary-cell">${escapeHtml(t.masuk_tanggal)}</div></td>
                <td>${escapeHtml(duration(t.menunggu_menit))}</td>
                <td>${statusBadge(t.status)}${officer}</td>
                <td style="text-align: right;">${actions(t)}</td>`;
            body.appendChild(row);
        });
        empty.style.display = (data.tickets || []).length ? 'none' : 'block';

        historyBody.innerHTML = '';
        (data.history || []).forEach(function (t) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${escapeHtml(t.nomor)}</strong></td>
                <td>${escapeHtml(t.nama)}</td>
                <td>${escapeHtml(t.topik)}</td>
                <td>${escapeHtml(t.masuk)}</td>
                <td>${escapeHtml(t.ditutup)}</td>
                <td>${statusBadge(t.status)}</td>
                <td>${escapeHtml(t.petugas || '-')}</td>
                <td class="text-secondary-cell">${escapeHtml(t.alasan || '')}</td>`;
            historyBody.appendChild(row);
        });
        historyEmpty.style.display = (data.history || []).length ? 'none' : 'block';

        const s = data.stats || {};
        document.getElementById('statMenunggu').textContent = s.menunggu ?? 0;
        document.getElementById('statDitangani').textContent = s.ditangani ?? 0;
        document.getElementById('statSelesai').textContent = s.selesai_hari_ini ?? 0;
        document.getElementById('statRataTunggu').textContent = s.rata_tunggu_menit != null ? `${s.rata_tunggu_menit} mnt` : '-';
        document.getElementById('statTertua').textContent = s.tertua_menit != null ? `Terlama menunggu ${duration(s.tertua_menit)}` : 'Antrean kosong';

        const badge = document.querySelector('[data-queue-badge]');
        if (badge) {
            badge.textContent = s.menunggu ?? 0;
            badge.style.display = s.menunggu ? '' : 'none';
        }
    }

    async function refresh() {
        try {
            render(await request('GET', config.urls.data));
        } catch (e) {
            console.error(e);
        }
    }

    async function act(target, payload, confirmText) {
        if (busy) return;
        if (confirmText && !window.confirm(confirmText)) return;
        busy = true;
        try {
            const data = await request('POST', target, payload);
            if (data.percakapan_url) {
                window.location.href = data.percakapan_url;
                return;
            }
            await refresh();
        } catch (e) {
            alert(e.message);
            await refresh();
        } finally {
            busy = false;
        }
    }

    body.addEventListener('click', function (e) {
        const button = e.target.closest('button[data-action]');
        if (!button) return;
        if (button.dataset.action === 'ambil') {
            act(button.dataset.url);
        } else if (button.dataset.action === 'selesai') {
            act(button.dataset.url, { kirim_pesan: true }, `Selesaikan tiket ${button.dataset.nomor}? Pemohon akan diberi tahu dan bot aktif kembali.`);
        } else if (button.dataset.action === 'pengaduan') {
            openComplaintDialog(current.find(t => String(t.id) === button.dataset.id));
        }
    });

    // ------------------------------------------------------------ jadikan pengaduan
    const dialog = document.getElementById('dialogPengaduan');
    const dialogForm = document.getElementById('formPengaduan');
    let dialogTicket = null;

    function openComplaintDialog(ticket) {
        if (!dialog || !ticket) return;
        dialogTicket = ticket;
        document.getElementById('dialogPengaduanTiket').textContent = ticket.nomor;
        document.getElementById('dialogIsi').value = ticket.pertanyaan_lengkap || '';
        if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
    }

    function closeComplaintDialog() {
        dialogTicket = null;
        if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
    }

    if (dialog && dialogForm) {
        document.getElementById('dialogBatal').addEventListener('click', closeComplaintDialog);

        dialogForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!dialogTicket || busy) return;
            const saveButton = document.getElementById('dialogSimpan');
            busy = true;
            saveButton.disabled = true;
            try {
                const data = await request('POST', dialogTicket.pengaduan_url, {
                    kategori: document.getElementById('dialogKategori').value,
                    isi: document.getElementById('dialogIsi').value.trim(),
                });
                closeComplaintDialog();
                alert(`Pengaduan ${data.pengaduan} dibuat. Nomor pengaduan sudah dikirim ke pemohon.`);
            } catch (err) {
                alert(err.message);
            } finally {
                busy = false;
                saveButton.disabled = false;
                refresh();
            }
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            act(config.urls.ambilBerikutnya);
        });
    }

    render(config);

    setInterval(function () {
        if (!document.hidden && !busy) refresh();
    }, config.pollMs || 8000);
});
