document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('faqSearch');
    const categoryFilter = document.getElementById('faqCategoryFilter');
    const statusFilter = document.getElementById('faqStatusFilter');
    const resetButton = document.getElementById('resetFaqFilter');

    const tableBody = document.getElementById('faqTableBody');
    const dataInfo = document.getElementById('faqDataInfo');
    const pagination = document.getElementById('faqPagination');

    if (!tableBody) return;

    const allRows = Array.from(tableBody.querySelectorAll('.faq-row'));
    const rowsPerPage = 6;
    let currentPage = 1;
    let filteredRows = [...allRows];

    function filterRows() {
        const keyword = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const selectedCategory = categoryFilter ? categoryFilter.value : '';
        const selectedStatus = statusFilter ? statusFilter.value : '';

        filteredRows = allRows.filter(function (row) {
            const rowText = row.textContent.toLowerCase();
            const rowCategory = row.dataset.category || '';
            const rowStatus = row.dataset.status || '';

            const matchesSearch = rowText.includes(keyword);
            const matchesCategory = !selectedCategory || rowCategory === selectedCategory;
            const matchesStatus = !selectedStatus || rowStatus === selectedStatus;

            return matchesSearch && matchesCategory && matchesStatus;
        });

        currentPage = 1;
        renderTable();
    }

    function renderTable() {
        allRows.forEach(function (row) {
            row.style.display = 'none';
        });

        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = Math.min(startIndex + rowsPerPage, filteredRows.length);

        filteredRows.slice(startIndex, endIndex).forEach(function (row, index) {
            row.style.display = 'table-row';
            const numberCell = row.querySelector('td:first-child');
            if (numberCell) {
                numberCell.textContent = startIndex + index + 1;
            }
        });

        if (dataInfo) {
            if (filteredRows.length === 0) {
                dataInfo.textContent = 'Tidak ada FAQ yang cocok.';
            } else {
                dataInfo.textContent = `Menampilkan ${startIndex + 1} - ${endIndex} dari 42 entri`;
            }
        }

        renderPagination();
    }

    function renderPagination() {
        if (!pagination) return;
        pagination.innerHTML = '';

        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);

        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'btn-page-nav';
        prevBtn.innerHTML = '&laquo;';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        });
        pagination.appendChild(prevBtn);

        // Page numbers
        for (let page = 1; page <= totalPages; page++) {
            const pageBtn = document.createElement('button');
            pageBtn.type = 'button';
            pageBtn.className = 'btn-page-num' + (page === currentPage ? ' active' : '');
            pageBtn.textContent = page;
            pageBtn.addEventListener('click', function () {
                currentPage = page;
                renderTable();
            });
            pagination.appendChild(pageBtn);
        }

        // Ellipsis & final page representation matching design if needed
        if (totalPages > 1 && totalPages < 7) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'pagination-ellipsis';
            ellipsis.textContent = '...';
            pagination.appendChild(ellipsis);

            const lastBtn = document.createElement('button');
            lastBtn.type = 'button';
            lastBtn.className = 'btn-page-num';
            lastBtn.textContent = '7';
            lastBtn.addEventListener('click', function () {
                alert('Menampilkan halaman 7');
            });
            pagination.appendChild(lastBtn);
        }

        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'btn-page-nav';
        nextBtn.innerHTML = '&raquo;';
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;
        nextBtn.addEventListener('click', function () {
            if (currentPage < totalPages) {
                currentPage++;
                renderTable();
            }
        });
        pagination.appendChild(nextBtn);
    }

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (categoryFilter) categoryFilter.addEventListener('change', filterRows);
    if (statusFilter) statusFilter.addEventListener('change', filterRows);

    if (resetButton) {
        resetButton.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (categoryFilter) categoryFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            filterRows();
        });
    }

    renderTable();
});
