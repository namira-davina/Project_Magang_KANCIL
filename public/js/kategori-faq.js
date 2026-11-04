document.addEventListener('DOMContentLoaded', function () {
    const searchInput   = document.getElementById('kategoriSearchInput');
    const tableBody     = document.getElementById('kategoriTableBody');
    const modalEdit     = document.getElementById('modalEditKategori');
    const formEdit      = document.getElementById('formEditKategori');
    const totalCount    = document.getElementById('kategoriTotalCount');
    const paginationInfo = document.getElementById('kategoriPaginationInfo');
    const inputId       = document.getElementById('editKategoriId');
    const inputNama     = document.getElementById('inputNamaKategori');
    const btnTutup      = document.getElementById('btnTutupModalKategori');
    const btnBatal      = document.getElementById('btnBatalModalKategori');

    // ── BUKA MODAL EDIT ──────────────────────────────────────────────────────
    document.querySelectorAll('.btn-edit-kategori').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id   = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');

            inputId.value   = id;
            inputNama.value = nama;

            // Set action form ke URL update yang benar
            formEdit.action = '/kategori-faq/' + id;

            modalEdit.style.display = 'flex';
            inputNama.focus();
        });
    });

    // ── TUTUP MODAL ──────────────────────────────────────────────────────────
    function tutupModal() {
        modalEdit.style.display = 'none';
        inputId.value   = '';
        inputNama.value = '';
    }

    if (btnTutup) btnTutup.addEventListener('click', tutupModal);
    if (btnBatal) btnBatal.addEventListener('click', tutupModal);

    // Klik di luar modal → tutup
    if (modalEdit) {
        modalEdit.addEventListener('click', function (e) {
            if (e.target === modalEdit) tutupModal();
        });
    }

    // ── VALIDASI SUBMIT EDIT ─────────────────────────────────────────────────
    if (formEdit) {
        formEdit.addEventListener('submit', function (e) {
            if (!inputNama.value.trim()) {
                e.preventDefault();
                inputNama.focus();
            }
            // Biarkan form submit secara normal ke server
        });
    }

    // ── PENCARIAN ────────────────────────────────────────────────────────────
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function () {
            const q    = this.value.toLowerCase().trim();
            const rows = tableBody.querySelectorAll('tr');
            let visibleCount = 0;

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                if (text.includes(q)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            updateCounts(visibleCount);
        });
    }

    // ── UPDATE COUNTER ────────────────────────────────────────────────────────
    function updateCounts(visible) {
        if (!tableBody) return;
        const total = visible !== undefined
            ? visible
            : tableBody.querySelectorAll('tr').length;

        if (totalCount)     totalCount.textContent     = total + ' data';
        if (paginationInfo) paginationInfo.textContent = 'Menampilkan 1 - ' + total + ' dari ' + total + ' entri';
    }
});
