document.addEventListener('DOMContentLoaded', function () {
    const threadItems = document.querySelectorAll('.chat-thread-item');
    const searchInput = document.getElementById('conversationSearch');
    const filterButtons = document.querySelectorAll('.conversation-filter-btn');

    const chatContactName = document.getElementById('chatContactName');
    const chatContactStatus = document.getElementById('chatContactStatus');
    const chatAvatar = document.getElementById('chatAvatar');
    const chatModeBadge = document.getElementById('chatModeBadge');

    const takeoverButton = document.getElementById('takeoverButton');
    const chatReplyInput = document.getElementById('chatReplyInput');
    const sendMessageButton = document.getElementById('sendMessageButton');
    const chatReplyForm = document.getElementById('chatReplyForm');
    const chatMessages = document.getElementById('chatMessages');

    let isTakeoverActive = false;

    // Filter pills
    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filterType = this.getAttribute('data-filter');
            applyFilters(filterType, searchInput ? searchInput.value.toLowerCase().trim() : '');
        });
    });

    // Search filter
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const activeFilter = document.querySelector('.conversation-filter-btn.active');
            const filterType = activeFilter ? activeFilter.getAttribute('data-filter') : 'all';
            applyFilters(filterType, this.value.toLowerCase().trim());
        });
    }

    function applyFilters(filterType, query) {
        threadItems.forEach(function (item) {
            const itemType = item.getAttribute('data-filter-type');
            const name = item.getAttribute('data-name').toLowerCase();
            const phone = item.getAttribute('data-phone').toLowerCase();
            const preview = item.querySelector('.thread-preview').textContent.toLowerCase();

            const matchesType = filterType === 'all' || itemType === filterType;
            const matchesQuery = !query || name.includes(query) || phone.includes(query) || preview.includes(query);

            item.style.display = (matchesType && matchesQuery) ? 'block' : 'none';
        });
    }

    // Select thread
    threadItems.forEach(function (item) {
        item.addEventListener('click', function () {
            threadItems.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const name = this.getAttribute('data-name');
            const phone = this.getAttribute('data-phone');
            const initial = this.getAttribute('data-initial') || name.substring(0, 2).toUpperCase();
            const badge = this.getAttribute('data-badge') || 'Aktif';

            if (chatContactName) chatContactName.textContent = name;
            if (chatContactStatus) chatContactStatus.textContent = phone;
            if (chatAvatar) chatAvatar.textContent = initial;
            if (chatModeBadge) {
                chatModeBadge.textContent = badge;
                chatModeBadge.className = 'badge-status-pill ' + (badge.includes('Petugas') ? 'waiting' : (badge.includes('Ditangani') ? 'agent' : 'bot'));
            }
        });
    });

    // Takeover Button
    if (takeoverButton) {
        takeoverButton.addEventListener('click', function () {
            isTakeoverActive = !isTakeoverActive;

            if (isTakeoverActive) {
                this.classList.add('btn-active-state');
                this.innerHTML = `
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                    <span>Kembalikan ke Bot</span>
                `;
                if (chatReplyInput) {
                    chatReplyInput.focus();
                    chatReplyInput.placeholder = 'Ketik instruksi resmi petugas ke pemohon WhatsApp...';
                }
            } else {
                this.classList.remove('btn-active-state');
                this.innerHTML = `
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Ambil Alih Percakapan</span>
                `;
                if (chatReplyInput) {
                    chatReplyInput.placeholder = 'Tulis instruksi resmi petugas ke WhatsApp pemohon...';
                }
            }
        });
    }

    // Submit reply message
    if (chatReplyForm) {
        chatReplyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const text = chatReplyInput.value.trim();
            if (!text) return;

            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const timeStr = `${hours}:${minutes}`;

            const bubbleRow = document.createElement('div');
            bubbleRow.className = 'chat-bubble-row sent';
            bubbleRow.innerHTML = `
                <div class="chat-bubble-sender text-right">Petugas: Agus Setiawan, S.IP. <span class="bubble-timestamp">${timeStr}</span></div>
                <div class="chat-bubble-content official">
                    <p>${escapeHtml(text)}</p>
                    <div class="official-sent-status">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Terkirim ke WhatsApp</span>
                    </div>
                </div>
            `;

            chatMessages.appendChild(bubbleRow);
            chatReplyInput.value = '';
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
    }

    function escapeHtml(string) {
        const div = document.createElement('div');
        div.textContent = string;
        return div.innerHTML;
    }
});
