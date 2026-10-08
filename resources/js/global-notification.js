const notification = document.querySelector('[data-global-notification]');

if (notification) {
    const title = notification.querySelector('[data-global-notification-title]');
    const message = notification.querySelector('[data-global-notification-message]');
    const detail = notification.querySelector('[data-global-notification-detail]');
    let dismissTimer;

    const hide = () => {
        notification.classList.remove('is-visible');
        window.setTimeout(() => {
            if (!notification.classList.contains('is-visible')) notification.hidden = true;
        }, 180);
    };

    document.addEventListener('idolranking:notify', (event) => {
        const data = event.detail || {};
        window.clearTimeout(dismissTimer);
        title.textContent = data.title || '';
        message.textContent = data.message || '';
        detail.textContent = data.detail || '';
        detail.hidden = !data.detail;
        notification.dataset.type = data.type || 'success';
        notification.hidden = false;
        requestAnimationFrame(() => notification.classList.add('is-visible'));
        dismissTimer = window.setTimeout(hide, Number(data.duration) || 2600);
    });

    if (notification.dataset.initialMessage) {
        document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: {
            title: notification.dataset.initialTitle,
            message: notification.dataset.initialMessage,
            type: notification.dataset.initialType,
        }}));
    } else {
        const stored = sessionStorage.getItem('idolranking:notification');
        if (stored) {
            sessionStorage.removeItem('idolranking:notification');
            try {
                document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: JSON.parse(stored) }));
            } catch {
                // Ignore malformed transient notification data.
            }
        }
    }
}
