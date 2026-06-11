const state = {
    socket: null,
    reconnectDelay: 1000,
    reconnectTimer: null,
    token: null,
    tokenExpiresAt: 0,
    reloadTimer: null,
};

document.addEventListener('DOMContentLoaded', () => {
    if (!('WebSocket' in window)) {
        return;
    }

    connectRealtime();
});

async function connectRealtime() {
    window.clearTimeout(state.reconnectTimer);

    const token = await getRealtimeToken();
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const query = token ? `?token=${encodeURIComponent(token)}` : '';
    const socket = new WebSocket(`${protocol}//${window.location.hostname}:8090/${query}`);

    state.socket = socket;

    socket.addEventListener('open', () => {
        state.reconnectDelay = 1000;
        socket.send(JSON.stringify({
            action: 'subscribe',
            topics: Array.from(getRealtimeTopics()),
        }));
    });

    socket.addEventListener('message', (event) => {
        handleRealtimeMessage(event.data);
    });

    socket.addEventListener('close', () => {
        scheduleReconnect();
    });

    socket.addEventListener('error', () => {
        socket.close();
    });
}

async function getRealtimeToken() {
    const userId = document.body.dataset.realtimeUserId;
    const tokenUrl = document.body.dataset.realtimeTokenUrl;

    if (!userId || !tokenUrl) {
        return null;
    }

    if (state.token && Date.now() < state.tokenExpiresAt - 30000) {
        return state.token;
    }

    try {
        const response = await fetch(tokenUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            return null;
        }

        const data = await response.json();
        state.token = data.token || null;
        state.tokenExpiresAt = Date.now() + ((data.expiresIn || 300) * 1000);

        return state.token;
    } catch {
        return null;
    }
}

function scheduleReconnect() {
    window.clearTimeout(state.reconnectTimer);
    state.reconnectTimer = window.setTimeout(() => {
        state.reconnectDelay = Math.min(state.reconnectDelay * 1.5, 10000);
        connectRealtime();
    }, state.reconnectDelay);
}

function getRealtimeTopics() {
    const topics = new Set(['public', 'search:availability']);

    document.querySelectorAll('[data-realtime-topic]').forEach((element) => {
        const topic = element.dataset.realtimeTopic;
        if (topic) {
            topics.add(topic);
        }
    });

    document.querySelectorAll('[data-realtime-property-id]').forEach((element) => {
        const propertyId = element.dataset.realtimePropertyId;
        if (propertyId) {
            topics.add(`property:${propertyId}:availability`);
        }
    });

    return topics;
}

function handleRealtimeMessage(rawMessage) {
    let message;
    try {
        message = JSON.parse(rawMessage);
    } catch {
        return;
    }

    if (!message || !message.type) {
        return;
    }

    document.dispatchEvent(new CustomEvent('app:realtime', { detail: message }));

    if (message.type === 'message.created') {
        handleMessageCreated(message.payload || {});
        return;
    }

    if (message.type === 'notification.created') {
        handleNotificationCreated(message.payload || {});
        return;
    }

    if (message.type === 'notifications.read') {
        updateNotificationBadge(message.payload?.unreadCount ?? 0);
        refreshNotificationPage();
        return;
    }

    if (message.type.startsWith('reservation.')) {
        handleReservationEvent(message.type, message.payload || {});
        return;
    }

    if (message.type === 'availability.updated') {
        handleAvailabilityUpdated(message.payload || {});
    }
}

function handleMessageCreated(payload) {
    const conversation = document.querySelector('[data-realtime-conversation-id]');
    const conversationId = conversation?.dataset.realtimeConversationId;

    if (conversationId && payload.conversationId === conversationId) {
        appendMessage(payload);
        return;
    }

    if (getRealtimeView() === 'messages-index') {
        schedulePageReload();
    }
}

function appendMessage(payload) {
    const list = document.querySelector('[data-realtime-message-list]');
    if (!list || !payload.messageId || list.querySelector(`[data-realtime-message-id="${payload.messageId}"]`)) {
        return;
    }

    list.querySelector('[data-realtime-empty]')?.remove();

    const isMine = payload.senderId === document.body.dataset.realtimeUserId;
    const row = document.createElement('div');
    row.dataset.realtimeMessageId = payload.messageId;
    row.className = `flex ${isMine ? 'justify-end' : 'justify-start'}`;

    const bubble = document.createElement('div');
    bubble.className = `max-w-[80%] rounded-2xl px-4 py-3 ${isMine ? 'bg-brand text-white' : 'bg-gray-100 text-gray-900'}`;

    const meta = document.createElement('p');
    meta.className = `text-xs font-semibold mb-1 ${isMine ? 'text-white/80' : 'text-gray-500'}`;
    meta.textContent = `${payload.senderName || 'Membre'} · ${payload.createdAtLabel || ''}`;

    const content = document.createElement('p');
    content.className = 'text-sm whitespace-pre-line';
    content.textContent = payload.content || '';

    bubble.append(meta, content);
    row.appendChild(bubble);
    list.appendChild(row);
    list.scrollTop = list.scrollHeight;
}

function handleNotificationCreated(payload) {
    if (typeof payload.unreadCount === 'number') {
        updateNotificationBadge(payload.unreadCount);
    } else {
        incrementNotificationBadge();
    }

    prependNotification(payload);
    showRealtimeToast(payload.title || 'Nouvelle notification', payload.content || '');
    refreshNotificationPage();
}

function updateNotificationBadge(count) {
    document.querySelectorAll('[data-realtime-notification-badge]').forEach((badge) => {
        badge.textContent = String(count);
        badge.classList.toggle('hidden', count <= 0);
    });
}

function incrementNotificationBadge() {
    document.querySelectorAll('[data-realtime-notification-badge]').forEach((badge) => {
        const current = Number.parseInt(badge.textContent || '0', 10) || 0;
        updateNotificationBadge(current + 1);
    });
}

function prependNotification(payload) {
    const list = document.querySelector('[data-realtime-notifications-list]');
    if (!list || !payload.notificationId) {
        return;
    }

    list.querySelector('[data-realtime-notifications-empty]')?.remove();

    const form = document.createElement('form');
    form.action = `/notifications/${payload.notificationId}/read`;
    form.method = 'POST';
    form.className = 'm-0';

    if (payload.csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = payload.csrfToken;
        form.appendChild(csrfInput);
    }

    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'w-full text-left px-4 py-3 hover:bg-gray-50 transition flex items-start gap-3 bg-brand/5';

    const dot = document.createElement('span');
    dot.className = 'mt-1.5 flex h-2 w-2 shrink-0 rounded-full bg-brand';

    const body = document.createElement('div');
    body.className = 'flex-1 min-w-0';

    const title = document.createElement('p');
    title.className = 'text-xs font-semibold text-gray-900 truncate text-brand';
    title.textContent = payload.title || 'Notification';

    const content = document.createElement('p');
    content.className = 'text-xs text-gray-500 mt-0.5 line-clamp-2';
    content.textContent = payload.content || '';

    const createdAt = document.createElement('p');
    createdAt.className = 'text-[10px] text-gray-400 mt-1';
    createdAt.textContent = payload.createdAtLabel || '';

    body.append(title, content, createdAt);
    button.append(dot, body);
    form.appendChild(button);
    list.prepend(form);

    while (list.children.length > 5) {
        list.lastElementChild?.remove();
    }
}

function handleReservationEvent(type, payload) {
    const view = getRealtimeView();

    if (view === 'host-reservations' || view === 'host-dashboard' || view === 'reservation-index') {
        showRealtimeToast('Réservation mise à jour', payload.propertyTitle || '');
        schedulePageReload();
        return;
    }

    const reservationDetail = document.querySelector('[data-realtime-reservation-id]');
    if (reservationDetail?.dataset.realtimeReservationId === payload.reservationId) {
        showRealtimeToast('Réservation mise à jour', statusLabel(payload.status));
        schedulePageReload();
        return;
    }

    if (type === 'reservation.confirmed' || type === 'reservation.cancelled') {
        refreshAvailabilityForProperty(payload.propertyId);
    }
}

function handleAvailabilityUpdated(payload) {
    const view = getRealtimeView();
    const propertyId = payload.propertyId;
    const propertyElement = document.querySelector('[data-realtime-property-id]');
    const currentPropertyId = propertyElement?.dataset.realtimePropertyId;

    if (view === 'host-calendar' && currentPropertyId === propertyId) {
        showRealtimeToast('Calendrier mis à jour', 'Les disponibilités ont changé.');
        schedulePageReload();
        return;
    }

    if (view === 'search-results') {
        schedulePageReload();
        return;
    }

    refreshAvailabilityForProperty(propertyId);
}

function refreshAvailabilityForProperty(propertyId) {
    const priceCalculator = document.querySelector(`[data-booking-price-property-id-value="${propertyId}"]`);
    if (!priceCalculator) {
        return;
    }

    const input = priceCalculator.querySelector('[data-booking-price-target="checkin"]');
    input?.dispatchEvent(new Event('change', { bubbles: true }));
}

function refreshNotificationPage() {
    if (getRealtimeView() === 'notifications-index') {
        schedulePageReload();
    }
}

function getRealtimeView() {
    return document.querySelector('[data-realtime-view]')?.dataset.realtimeView || '';
}

function schedulePageReload() {
    window.clearTimeout(state.reloadTimer);
    state.reloadTimer = window.setTimeout(() => {
        window.location.reload();
    }, 700);
}

function statusLabel(status) {
    const labels = {
        pending: 'En attente',
        confirmed: 'Confirmée',
        completed: 'Terminée',
        cancelled: 'Annulée',
    };

    return labels[status] || '';
}

function showRealtimeToast(title, message) {
    let root = document.querySelector('[data-realtime-toast-root]');
    if (!root) {
        root = document.createElement('div');
        root.dataset.realtimeToastRoot = '';
        root.className = 'fixed right-4 top-4 z-50 space-y-2 max-w-sm';
        document.body.appendChild(root);
    }

    const toast = document.createElement('div');
    toast.className = 'rounded-xl border border-gray-200 bg-white shadow-lg px-4 py-3 text-sm text-gray-800';

    const toastTitle = document.createElement('p');
    toastTitle.className = 'font-semibold text-gray-950';
    toastTitle.textContent = title;

    const toastMessage = document.createElement('p');
    toastMessage.className = 'text-xs text-gray-500 mt-1';
    toastMessage.textContent = message;

    toast.append(toastTitle, toastMessage);
    root.appendChild(toast);

    window.setTimeout(() => {
        toast.remove();
    }, 4500);
}
