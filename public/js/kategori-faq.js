document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('kategoriSearchInput');
    const tableBody = document.getElementById('kategoriTableBody');
    const modalEdit = document.getElementById('modalEditKategori');
    const btnTutup = document.getElementById('btnTutupModalKategori');
    const btnBatal = document.getElementById('btnBatalModalKategori');
    const formEdit = document.getElementById('formEditKategori');
    const inputId = document.getElementById('editKategoriId');
    const inputNama = document.getElementById('inputNamaKategori');
    const totalCount = document.getElementById('kategoriTotalCount');
    const paginationInfo = document.getElementById('kategoriPaginationInfo');

    function openModal(id, currentName) {
        if (!modalEdit) return;
        inputId.value = id;
        inputNama.value = currentName;
        modalEdit.style.display = 'flex';
        inputNama.focus();
    }

    function closeModal() {
        if (!modalEdit) return;
        modalEdit.style.display = 'none';
        inputId.value = '';
        inputNama.value = '';
    }

    // Attach click to Edit buttons
    document.querySelectorAll('.btn-edit-kategori').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const row = this.closest('tr');
            const nama = row ? row.querySelector('.font-medium').textContent.trim() : '';
            openModal(id, nama);
        });
    });

    // Attach click to Delete buttons
    document.querySelectorAll('.btn-delete-kategori').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const row = this.closest('tr');
            const nama = row ? row.querySelector('.font-medium').textContent.trim() : 'kategori ini';
            if (confirm(`Apakah Anda yakin ingin menghapus kategori "${nama}"?`)) {
                if (row) row.remove();
                updateCounts();
            }
        });
    });

    if (btnTutup) btnTutup.addEventListener('click', closeModal);
    if (btnBatal) btnBatal.addEventListener('click', closeModal);

    if (modalEdit) {
        modalEdit.addEventListener('click', function (e) {
            if (e.target === modalEdit) {
                closeModal();
            }
        });
    }

    if (formEdit) {
        formEdit.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = inputId.value;
            const newName = inputNama.value.trim();
            if (!newName) return;

            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                const nameCol = row.querySelector('.font-medium');
                if (nameCol) nameCol.textContent = newName;
                row.setAttribute('data-nama', newName);
            }

            closeModal();
            alert(`Nama kategori berhasil diperbarui menjadi "${newName}".`);
        });
    }

    // Search filter
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
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

            if (paginationInfo) {
                paginationInfo.textContent = `Menampilkan 1 - ${visibleCount} dari ${visibleCount} entri`;
            }
        });
    }

    function updateCounts() {
        if (!tableBody) return;
        const total = tableBody.querySelectorAll('tr').length;
        if (totalCount) totalCount.textContent = `${total} data`;
        if (paginationInfo) paginationInfo.textContent = `Menampilkan 1 - ${total} dari ${total} entri`;
    }
});
