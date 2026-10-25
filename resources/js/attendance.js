const checkInForm = document.querySelector('[data-attendance-check-in-form]');

function insertAttendanceCard(cardHtml, checkInId) {
    if (typeof cardHtml !== 'string' || !/^\d+$/.test(String(checkInId))) return false;

    try {
        const feed = document.querySelector('[data-attendance-feed]');
        if (!feed) return false;

        const cardId = `attendance-card-${checkInId}`;
        if (document.getElementById(cardId)) return true;

        let grid = feed.querySelector('.attendance-grid');
        if (!grid) {
            feed.querySelector('.attendance-empty')?.remove();
            grid = document.createElement('div');
            grid.className = 'attendance-grid';
            const pagination = feed.querySelector('.number-pagination');
            feed.insertBefore(grid, pagination || null);
        }

        grid.insertAdjacentHTML('afterbegin', cardHtml);
        const insertedCard = document.getElementById(cardId);
        if (!insertedCard) return false;
        initializeAttendanceTranslation(insertedCard);
        return true;
    } catch {
        // The check-in is already committed; feed rendering can recover on the next page load.
        return false;
    }
}

function initializeAttendanceTranslation(card) {
    const content = card.querySelector('[data-attendance-content]');
    const toggle = card.querySelector('[data-attendance-translate-toggle]');
    const status = card.querySelector('[data-attendance-translation-status]');
    const original = content?.textContent ?? '';
    let translated = false;

    if (!toggle || toggle.dataset.translationInitialized === 'true') return;
    toggle.dataset.translationInitialized = 'true';

    toggle.addEventListener('click', async () => {
        if (translated) {
            content.textContent = original;
            toggle.textContent = window.idolRankingT('translate');
            toggle.setAttribute('aria-label', window.idolRankingT('translate'));
            toggle.setAttribute('aria-expanded', 'false');
            translated = false;
            status.textContent = '';
            status.hidden = true;
            return;
        }

        toggle.disabled = true;
        status.textContent = window.idolRankingT('translation_loading');
        status.hidden = false;

        try {
            const url = new URL(card.dataset.endpoint, window.location.origin);
            url.searchParams.set('language', card.dataset.targetLanguage);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || typeof payload.data?.content !== 'string') {
                status.textContent = payload.message || window.idolRankingT('attendance_error');
                return;
            }

            content.textContent = payload.data.content;
            toggle.textContent = window.idolRankingT('show_original');
            toggle.setAttribute('aria-label', window.idolRankingT('show_original'));
            toggle.setAttribute('aria-expanded', 'true');
            status.textContent = '';
            status.hidden = true;
            translated = true;
        } catch {
            status.textContent = window.idolRankingT('attendance_error');
        } finally {
            toggle.disabled = false;
            status.hidden = status.textContent === '';
        }
    });
}

if (checkInForm) {
    const submit = checkInForm.querySelector('[data-attendance-submit]');
    const result = checkInForm.querySelector('[data-attendance-result]');

    checkInForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!submit || submit.disabled) return;

        submit.disabled = true;
        const originalLabel = submit.textContent;
        let completed = false;
        submit.textContent = window.idolRankingT('processing');
        result?.classList.remove('is-error');
        if (result) result.hidden = true;

        try {
            const response = await fetch(checkInForm.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': checkInForm.dataset.csrf,
                },
                body: JSON.stringify({
                    content: checkInForm.elements.content.value,
                    language_code: checkInForm.elements.language_code.value,
                }),
            });
            const payload = await response.json().catch(() => ({}));
            const message = payload.errors?.content?.[0] || payload.errors?.language_code?.[0]
                || payload.message || window.idolRankingT('please_try_again');

            if (response.ok || payload.already_claimed) {
                const data = payload.data || {};
                const completedPanel = document.createElement('div');
                completedPanel.className = 'attendance-complete';
                completedPanel.setAttribute('data-attendance-completed', '');
                completedPanel.setAttribute('role', 'status');

                const heading = document.createElement('strong');
                heading.textContent = checkInForm.dataset.completedLabel;
                completedPanel.appendChild(heading);

                if (data.reward_awarded === true && Number.isInteger(Number(data.amount))) {
                    const reward = document.createElement('span');
                    const heartIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    heartIcon.setAttribute('class', 'heart-icon--small');
                    heartIcon.setAttribute('aria-hidden', 'true');
                    const heartUse = document.createElementNS('http://www.w3.org/2000/svg', 'use');
                    heartUse.setAttribute('href', '#icon-heart');
                    heartIcon.appendChild(heartUse);
                    reward.append(heartIcon, document.createTextNode(`+${new Intl.NumberFormat().format(Number(data.amount))}`));
                    completedPanel.appendChild(reward);
                }

                const checkInMessage = document.createElement('p');
                checkInMessage.className = 'attendance-complete-message';
                checkInMessage.textContent = typeof data.content === 'string'
                    ? data.content : checkInForm.elements.content.value;
                completedPanel.appendChild(checkInMessage);
                submit.textContent = originalLabel;
                checkInForm.replaceWith(completedPanel);
                completed = true;
                insertAttendanceCard(data.card_html, data.check_in_id);
            } else {
                if (result) {
                    result.textContent = message;
                    result.hidden = false;
                    result.classList.add('is-error');
                }
            }

            document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: {
                type: response.ok || payload.already_claimed ? 'success' : 'error',
                title: response.ok || payload.already_claimed ? window.idolRankingT('daily_checkin') : window.idolRankingT('checkin_failed'),
                message,
            }}));
        } catch {
            if (result) {
                result.textContent = window.idolRankingT('attendance_error');
                result.hidden = false;
                result.classList.add('is-error');
            }
        } finally {
            if (!completed && checkInForm.isConnected) {
                submit.disabled = false;
                submit.textContent = originalLabel;
            }
        }
    });
}

document.querySelectorAll('[data-attendance-translation]').forEach(initializeAttendanceTranslation);
