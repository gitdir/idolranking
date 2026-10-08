const form = document.querySelector('[data-fan-post-comment-form]');

if (form) {
    const textarea = form.querySelector('[name="content"]');
    const requestKey = form.querySelector('[name="request_key"]');
    const submit = form.querySelector('[type="submit"]');
    const list = document.querySelector('[data-fan-post-comment-list]');
    const message = document.querySelector('[data-fan-post-comment-message]');
    const counters = document.querySelectorAll('[data-fan-post-comment-count]');
    const pagination = document.querySelector('.fan-detail-comments > .number-pagination');

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
    newRequestKey();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.signedIn !== 'true') {
            document.dispatchEvent(new CustomEvent('idolranking:sign-in'));
            return;
        }
        if (!textarea.value.trim()) {
            showMessage('Please write a comment.', true);
            textarea.focus();
            return;
        }

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
                showMessage(result.message ?? Object.values(result.errors ?? {})[0]?.[0] ?? 'Unable to post the comment.', true);
                return;
            }

            if (Number(result.data?.rewarded_hearts) > 0) {
                sessionStorage.setItem('idolranking:notification', JSON.stringify({
                    title: 'Comment posted',
                    message: result.message,
                    type: 'success',
                }));
            }

            // Reload page 1 so the server remains the single source of truth for
            // the list window, total count, and pagination that may have just appeared.
            const commentsUrl = new URL(window.location.href);
            commentsUrl.searchParams.set('comments_page', '1');
            commentsUrl.hash = 'comments';
            window.location.assign(commentsUrl);
        } catch (error) {
            showMessage('Unable to post the comment. Please try again.', true);
        } finally {
            submit.disabled = false;
        }
    });

    list.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-comment-delete]');
        if (!button || !confirm('Delete this comment?')) return;

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
                showMessage(result.message ?? 'Unable to delete the comment.', true);
                button.disabled = false;
                return;
            }
            button.closest('li').remove();
            updateCount(result.data.comment_count);
            showMessage(result.message);
        } catch (error) {
            showMessage('Unable to delete the comment. Please try again.', true);
            button.disabled = false;
        }
    });
}
