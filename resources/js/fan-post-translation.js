const control = document.querySelector('[data-fan-post-translation]');

if (control) {
    const button = control.querySelector('[data-translation-toggle]');
    const status = control.querySelector('[data-translation-status]');
    const title = document.querySelector('[data-fan-post-title]');
    const content = document.querySelector('[data-fan-post-content]');
    const originalTitle = title.textContent;
    const originalContent = content.textContent;
    let cachedTranslation = null;
    let showingTranslation = false;

    const setStatus = (message = '', isError = false) => {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
        status.hidden = message === '';
    };

    const showOriginal = () => {
        title.textContent = originalTitle;
        content.textContent = originalContent;
        showingTranslation = false;
        button.textContent = window.idolRankingT('translate');
        button.setAttribute('aria-expanded', 'false');
        setStatus();
    };

    const showTranslation = () => {
        title.textContent = cachedTranslation.title;
        content.textContent = cachedTranslation.content ?? '';
        showingTranslation = true;
        button.textContent = window.idolRankingT('show_original');
        button.setAttribute('aria-expanded', 'true');
        setStatus();
    };

    button.addEventListener('click', async () => {
        if (showingTranslation) {
            showOriginal();
            return;
        }

        if (cachedTranslation) {
            showTranslation();
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setStatus(window.idolRankingT('translation_loading'));

        try {
            const url = new URL(control.dataset.endpoint, window.location.origin);
            url.searchParams.set('language', control.dataset.targetLanguage);
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json();

            if (!response.ok || !result.success || typeof result.data?.title !== 'string') {
                const fallback = result.code === 'translation_unavailable'
                    ? 'translation_unavailable'
                    : 'translation_error';
                setStatus(result.message || window.idolRankingT(fallback), true);
                return;
            }

            cachedTranslation = result.data;
            showTranslation();
        } catch (error) {
            setStatus(window.idolRankingT('translation_error'), true);
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
}
