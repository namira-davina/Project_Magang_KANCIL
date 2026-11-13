document.addEventListener('DOMContentLoaded', function () {
    const config = window.KANCIL_PERCAKAPAN || { threads: [], counts: {}, urls: {}, canReply: false, pollMs: 8000 };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const threadList = document.getElementById('conversationList');
    const emptyNotice = document.getElementById('conversationEmpty');
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

    let threads = config.threads || [];
    let selectedId = config.selectedId || (threads[0] ? threads[0].id : null);
    let currentConversation = null;
    let lastMessageSignature = '';
    let sending = false;

    const ICON_TAKEOVER = `
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span>Ambil Alih Percakapan</span>`;
    const ICON_RELEASE = `
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="1 4 1 10 7 10"></polyline>
            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
        </svg>
        <span>Kembalikan ke Bot</span>`;
    const ICON_CHECK = `
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>`;
    const ICON_BOT = `
        <svg class="sender-inline-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="10" rx="2"></rect>
            <circle cx="12" cy="5" r="2"></circle>
            <path d="M12 7v4"></path>
            <line x1="8" y1="16" x2="8" y2="16"></line>
            <line x1="16" y1="16" x2="16" y2="16"></line>
        </svg>`;
    const ICON_OFFICER = `
        <svg class="sender-inline-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
        </svg>`;

    function url(name, id) {
        return (config.urls[name] || '').replace('__ID__', id);
    }

    async function request(method, target, body) {
        const response = await fetch(target, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body ? JSON.stringify(body) : undefined,
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

    function escapeHtml(string) {
        const div = document.createElement('div');
        div.textContent = string == null ? '' : String(string);
        return div.innerHTML;
    }

    // Format WhatsApp (*tebal*, _miring_) + baris baru, setelah di-escape.
    function formatText(text) {
        return escapeHtml(text)
            .replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>')
            .replace(/(^|\s)_([^_\n]+)_(?=\s|$)/g, '$1<em>$2</em>')
            .replace(/\n/g, '<br>');
    }

    // ------------------------------------------------------------ daftar percakapan

    function activeFilter() {
        const btn = document.querySelector('.conversation-filter-btn.active');
        return btn ? btn.getAttribute('data-filter') : 'all';
    }

    // Filter "Perlu Petugas": tiket menunggu urut FIFO (paling awal masuk di atas), lalu yang sedang ditangani.
    function orderedThreads() {
        if (activeFilter() !== 'human') return threads;
        const waiting = threads.filter(t => t.ticket && t.ticket.status === 'Menunggu')
            .sort((a, b) => (a.ticket.queued_ts - b.ticket.queued_ts) || (a.ticket.id - b.ticket.id));
        return waiting.concat(threads.filter(t => !waiting.includes(t)));
    }

    function ticketTag(t) {
        if (!t.ticket) return '';
        const position = t.ticket.position ? ` • antrean ke-${t.ticket.position}` : (t.ticket.status === 'Ditangani' ? ' • ditangani' : '');
        return `<span class="thread-queue-tag">${escapeHtml(t.ticket.number)}${escapeHtml(position)}</span>`;
    }

    function renderThreads() {
        threadList.querySelectorAll('.chat-thread-item').forEach(el => el.remove());

        orderedThreads().forEach(function (t) {
            const item = document.createElement('div');
            item.className = 'chat-thread-item' + (t.id === selectedId ? ' active' : '');
            item.dataset.id = t.id;
            item.dataset.name = t.name;
            item.dataset.phone = t.phone;
            item.dataset.filterType = t.filter;
            item.innerHTML = `
                <div class="thread-top-row">
                    <strong class="thread-user-name">${escapeHtml(t.name)}${t.unread > 0 ? ` <span class="badge-status-pill waiting">${t.unread}</span>` : ''}</strong>
                    <span class="thread-time">${escapeHtml(t.time)}</span>
                </div>
                <div class="thread-phone">${escapeHtml(t.phone)}</div>
                <p class="thread-preview">${escapeHtml(t.preview)}</p>
                <div class="thread-footer-row">
                    <span class="badge-status-pill ${escapeHtml(t.badge_class)}">${escapeHtml(t.badge)}</span>
                    <span class="thread-ticket-id">${escapeHtml(t.code)}</span>
                </div>
                ${t.ticket ? `<div class="thread-footer-row">${ticketTag(t)}</div>` : ''}`;
            item.addEventListener('click', function () {
                selectThread(t.id);
            });
            threadList.appendChild(item);
        });

        if (emptyNotice) emptyNotice.style.display = threads.length ? 'none' : 'block';
        applyFilters();
    }

    function renderCounts(counts) {
        ['all', 'bot', 'human'].forEach(function (key) {
            const el = document.querySelector(`[data-count="${key}"]`);
            if (el && counts && counts[key] !== undefined) el.textContent = Number(counts[key]).toLocaleString('id-ID');
        });

        const badge = document.querySelector('[data-queue-badge]');
        if (badge && counts && counts.queue !== undefined) {
            badge.textContent = counts.queue;
            badge.style.display = counts.queue ? '' : 'none';
        }
    }

    function applyFilters() {
        const filterType = activeFilter();
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

        threadList.querySelectorAll('.chat-thread-item').forEach(function (item) {
            const name = (item.dataset.name || '').toLowerCase();
            const phone = (item.dataset.phone || '').toLowerCase();
            const preview = item.querySelector('.thread-preview').textContent.toLowerCase();

            const matchesType = filterType === 'all' || item.dataset.filterType === filterType;
            const matchesQuery = !query || name.includes(query) || phone.includes(query) || preview.includes(query);

            item.style.display = (matchesType && matchesQuery) ? 'block' : 'none';
        });
    }

    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            renderThreads();
        });
    });

    if (searchInput) searchInput.addEventListener('input', applyFilters);

    // ------------------------------------------------------------ isi chat

    function renderHeader(conv) {
        if (!conv) return;
        chatContactName.textContent = conv.name;
        const ticket = conv.ticket
            ? ` • Tiket ${conv.ticket.number}` + (conv.ticket.position ? ` (antrean ke-${conv.ticket.position})` : '')
            : '';
        const officer = conv.mode === 'HUMAN_HANDLING' && conv.assigned_name ? ` • ditangani ${conv.assigned_name}` : '';
        chatContactStatus.textContent = conv.phone + ticket + officer + (conv.paused_until ? ` • bot jeda s.d. ${conv.paused_until}` : '');
        chatAvatar.textContent = conv.initial;
        chatModeBadge.style.display = '';
        chatModeBadge.textContent = conv.header_badge;
        chatModeBadge.className = 'badge-status-pill ' + conv.badge_class;

        if (takeoverButton) {
            const handling = conv.mode === 'HUMAN_HANDLING';
            takeoverButton.disabled = false;
            takeoverButton.classList.toggle('btn-active-state', handling);
            takeoverButton.innerHTML = handling ? ICON_RELEASE : ICON_TAKEOVER;
            const label = takeoverButton.querySelector('span');
            if (label && conv.ticket) {
                label.textContent = handling ? 'Selesaikan Tiket & Kembalikan ke Bot' : 'Ambil Tiket Antrean';
            }
        }

        if (chatReplyInput) {
            chatReplyInput.placeholder = conv.mode === 'HUMAN_HANDLING'
                ? 'Ketik instruksi resmi petugas ke pemohon WhatsApp...'
                : 'Tulis instruksi resmi petugas ke WhatsApp pemohon...';
        }
    }

    function deliveryLabel(m) {
        switch (m.delivery_status) {
            case 'queued': return 'Mengirim...';
            case 'failed': return 'Gagal terkirim' + (m.delivery_error ? `: ${m.delivery_error}` : '');
            case 'delivered': return 'Tersampaikan';
            case 'read': return 'Dibaca';
            case 'sent': return 'Terkirim ke WhatsApp';
            default: return 'Tersimpan';
        }
    }

    function bubble(m) {
        const row = document.createElement('div');
        const attachment = m.attachment_url
            ? `<p style="margin-top: 6px;"><a href="${escapeHtml(m.attachment_url)}" target="_blank" rel="noopener">Lihat lampiran</a></p>`
            : '';

        if (m.sender_type === 'petugas') {
            row.className = 'chat-bubble-row sent';
            row.innerHTML = `
                <div class="chat-bubble-sender text-right">${ICON_OFFICER}${escapeHtml(m.sender)} <span class="bubble-timestamp">${escapeHtml(m.time)}</span></div>
                <div class="chat-bubble-content official">
                    <p>${formatText(m.text)}</p>${attachment}
                    <div class="official-sent-status">${ICON_CHECK}<span>${escapeHtml(deliveryLabel(m))}</span></div>
                </div>`;
        } else if (m.sender_type === 'user') {
            row.className = 'chat-bubble-row received';
            row.innerHTML = `
                <div class="chat-bubble-sender">${escapeHtml(m.sender)} <span class="bubble-timestamp">${escapeHtml(m.time)}</span></div>
                <div class="chat-bubble-content citizen">${formatText(m.text)}${attachment}</div>`;
        } else {
            row.className = 'chat-bubble-row sent bot-response';
            const status = m.delivery_status === 'failed' ? `<p style="margin-top: 6px; color: #dc2626;">${escapeHtml(deliveryLabel(m))}</p>` : '';
            row.innerHTML = `
                <div class="chat-bubble-sender text-right">${ICON_BOT}${escapeHtml(m.sender)}${m.faq_code ? ` • ${escapeHtml(m.faq_code)}` : ''} <span class="bubble-timestamp">${escapeHtml(m.time)}</span></div>
                <div class="chat-bubble-content bot"><p>${formatText(m.text)}</p>${attachment}${status}</div>`;
        }

        return row;
    }

    function renderMessages(messages) {
        const signature = messages.map(m => `${m.id}:${m.delivery_status}`).join('|');
        if (signature === lastMessageSignature) return;
        lastMessageSignature = signature;

        const nearBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 80;
        chatMessages.innerHTML = '';
        let lastDate = null;

        if (!messages.length) {
            chatMessages.innerHTML = '<div class="chat-date-pill-divider"><span>Belum ada pesan.</span></div>';
            return;
        }

        messages.forEach(function (m) {
            if (m.date && m.date !== lastDate) {
                const divider = document.createElement('div');
                divider.className = 'chat-date-pill-divider';
                divider.innerHTML = `<span>${escapeHtml(m.date)}</span>`;
                chatMessages.appendChild(divider);
                lastDate = m.date;
            }
            chatMessages.appendChild(bubble(m));
        });

        if (nearBottom || !currentConversation) chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    async function loadMessages(id, scroll) {
        try {
            const data = await request('GET', url('pesan', id));
            if (id !== selectedId) return;
            const first = !currentConversation || currentConversation.id !== id;
            currentConversation = data.conversation;
            renderHeader(data.conversation);
            if (first) lastMessageSignature = '';
            renderMessages(data.messages);
            if (scroll || first) chatMessages.scrollTop = chatMessages.scrollHeight;

            const t = threads.find(x => x.id === id);
            if (t && t.unread) {
                t.unread = 0;
                renderThreads();
            }
        } catch (e) {
            console.error(e);
        }
    }

    function selectThread(id) {
        selectedId = id;
        currentConversation = null;
        threadList.querySelectorAll('.chat-thread-item').forEach(function (el) {
            el.classList.toggle('active', Number(el.dataset.id) === id);
        });
        loadMessages(id, true);
    }

    // ------------------------------------------------------------ aksi petugas

    if (takeoverButton) {
        takeoverButton.addEventListener('click', async function () {
            if (!selectedId || !currentConversation) return;
            const handling = currentConversation.mode === 'HUMAN_HANDLING';
            if (handling && currentConversation.ticket
                && !window.confirm(`Selesaikan tiket ${currentConversation.ticket.number}? Pemohon akan diberi tahu dan bot aktif kembali.`)) {
                return;
            }
            takeoverButton.disabled = true;
            try {
                const data = await request('POST', url(handling ? 'kembalikan' : 'ambilAlih', selectedId));
                currentConversation = data.conversation;
                renderHeader(data.conversation);
                if (!handling && chatReplyInput) chatReplyInput.focus();
                refreshThreads();
            } catch (e) {
                alert(e.message);
            } finally {
                takeoverButton.disabled = false;
            }
        });
    }

    if (chatReplyForm) {
        chatReplyForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const text = chatReplyInput.value.trim();
            if (!text || !selectedId || sending) return;

            sending = true;
            if (sendMessageButton) sendMessageButton.disabled = true;
            try {
                const data = await request('POST', url('balas', selectedId), { pesan: text });
                chatReplyInput.value = '';
                currentConversation = data.conversation;
                renderHeader(data.conversation);
                await loadMessages(selectedId, true);
                refreshThreads();
            } catch (err) {
                alert(err.message);
            } finally {
                sending = false;
                if (sendMessageButton) sendMessageButton.disabled = false;
            }
        });

        chatReplyInput?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                chatReplyForm.requestSubmit();
            }
        });
    }

    // ------------------------------------------------------------ polling

    async function refreshThreads() {
        try {
            const data = await request('GET', config.urls.data);
            threads = data.threads;
            renderThreads();
            renderCounts(data.counts);
        } catch (e) {
            console.error(e);
        }
    }

    renderThreads();
    renderCounts(config.counts);
    if (selectedId) selectThread(selectedId);

    setInterval(function () {
        if (document.hidden) return;
        refreshThreads();
        if (selectedId) loadMessages(selectedId, false);
    }, config.pollMs || 8000);
});
