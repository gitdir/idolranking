const form = document.querySelector('[data-fan-post-comment-form]');

if (form) {
    const textarea = form.querySelector('[name="content"]');
    const requestKey = form.querySelector('[name="request_key"]');
    const submit = form.querySelector('[type="submit"]');
    const list = document.querySelector('[data-fan-post-comment-list]');
    const message = document.querySelector('[data-fan-post-comment-message]');
    const counters = document.querySelectorAll('[data-fan-post-comment-count]');
    const pagination = document.querySelector('.fan-detail-comments > .number-pagination');
    let attemptedContent = null;

    const newRequestKey = () => {
        requestKey.value = globalThis.crypto?.randomUUID?.()
            ?? `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
    };
    const showMessage = (text, isError = false) => {
        message.textContent = text;
        message.classList.toggle('is-error', isError);
        message.hidden = false;
    };
    const updateCount = (count) => {
        counters.forEach((counter) => { counter.textContent = Number(count).toLocaleString('en-US'); });
        if (pagination && Number(count) <= 10) pagination.hidden = true;
    };
    const makeElement = (tag, className, text = null) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== null) element.textContent = text;
        return element;
    };
    const renderComment = (comment, rewardRecorded, rewardedHearts) => {
        if (!comment || !list || [...list.children].some((item) => item.dataset.commentIdx === String(comment.idx))) return;

        const item = makeElement('li');
        item.dataset.commentIdx = String(comment.idx);

        const avatar = makeElement('img');
        avatar.src = comment.image_url;
        avatar.width = 38;
        avatar.height = 38;
        avatar.alt = '';
        avatar.loading = 'lazy';
        const profileLink = makeElement('a', 'member-identity-link');
        profileLink.href = comment.member_url;
        profileLink.setAttribute('aria-label', comment.member_label ?? comment.nickname);
        profileLink.append(avatar);
        item.append(profileLink);

        const content = makeElement('div', 'fan-detail-comment-content');
        const heading = makeElement('h3');
        const nickname = makeElement('a', 'member-identity-link', comment.nickname);
        nickname.setAttribute('translate', 'no');
        nickname.href = comment.member_url;
        const time = makeElement('small', 'fan-detail-time', comment.created_label);
        heading.append(nickname, document.createTextNode(' '), time);
        content.append(heading);

        const sourceLanguage = String(comment.language_code ?? '').toLowerCase();
        const targetLanguage = String(comment.translation_target_language ?? '').toLowerCase();
        if (sourceLanguage && targetLanguage && sourceLanguage !== targetLanguage && comment.translation_url) {
            const translation = makeElement('div', 'fan-post-comment-translation');
            translation.dataset.commentTranslation = '';
            translation.dataset.endpoint = comment.translation_url;
            translation.dataset.targetLanguage = targetLanguage;
            const body = makeElement('p', 'fan-post-comment-body');
            const bodyText = makeElement('span', '', comment.content);
            bodyText.id = `fan-post-comment-${comment.idx}-content`;
            const toggle = makeElement('button', '', window.idolRankingT('translate'));
            toggle.type = 'button';
            toggle.dataset.commentTranslationToggle = '';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-controls', bodyText.id);
            body.append(bodyText, document.createTextNode(' '), toggle);
            const status = makeElement('p');
            status.dataset.commentTranslationStatus = '';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            status.hidden = true;
            translation.append(body, status);
            content.append(translation);
        } else {
            content.append(makeElement('p', '', comment.content));
        }

        if (rewardRecorded) {
            content.append(makeElement('span', 'fan-detail-comment-reward', `+${Number(rewardedHearts).toLocaleString()} Heart`));
        }
        item.append(content);

        if (comment.can_delete && comment.delete_url) {
            const remove = makeElement('button', 'fan-detail-comment-delete', comment.delete_label ?? '');
            remove.type = 'button';
            remove.dataset.commentDelete = '';
            remove.dataset.endpoint = comment.delete_url;
            remove.dataset.csrf = form.querySelector('[name="_token"]')?.value ?? '';
            item.append(remove);
        }

        list.prepend(item);
    };
    newRequestKey();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.signedIn !== 'true') {
            document.dispatchEvent(new CustomEvent('idolranking:sign-in'));
            return;
        }
        if (!textarea.value.trim()) {
            showMessage(window.idolRankingT('write_comment_required'), true);
            textarea.focus();
            return;
        }

        // Reuse the key only for an unchanged retry after an uncertain network result.
        // A changed draft is a new comment and must not reuse the prior idempotency key.
        if (attemptedContent !== null && attemptedContent !== textarea.value) newRequestKey();
        attemptedContent = textarea.value;

        submit.disabled = true;
        message.hidden = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                if (response.status === 409) {
                    newRequestKey();
                    attemptedContent = null;
                }
                showMessage(result.message ?? Object.values(result.errors ?? {})[0]?.[0] ?? window.idolRankingT('comment_failed'), true);
                return;
            }

            // Rotate after confirmation so a restored page cannot resubmit the completed key.
            newRequestKey();
            attemptedContent = null;
            renderComment(result.data?.comment, result.data?.reward_recorded === true, result.data?.rewarded_hearts ?? 0);
            updateCount(result.data?.comment_count ?? 0);
            textarea.value = '';
            showMessage(result.message ?? window.idolRankingT('comment_posted'));

            if (Number(result.data?.rewarded_hearts) > 0) {
                try {
                    sessionStorage.setItem('idolranking:notification', JSON.stringify({
                        title: window.idolRankingT('comment_posted'),
                        message: result.message,
                        type: 'success',
                    }));
                } catch (error) {
                    // The inline success state remains available when browser storage is disabled.
                }
            }
        } catch (error) {
            showMessage(window.idolRankingT('comment_try_again'), true);
        } finally {
            submit.disabled = false;
        }
    });

    list.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-comment-delete]');
        if (!button || !confirm(window.idolRankingT('confirm_delete_comment'))) return;

        button.disabled = true;
        message.hidden = true;
        try {
            const response = await fetch(button.dataset.endpoint, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': button.dataset.csrf,
                },
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                showMessage(result.message ?? window.idolRankingT('delete_comment_failed'), true);
                button.disabled = false;
                return;
            }
            button.closest('li').remove();
            updateCount(result.data.comment_count);
            showMessage(result.message);
        } catch (error) {
            showMessage(window.idolRankingT('delete_comment_try_again'), true);
            button.disabled = false;
        }
    });
}
