const translationStates = new WeakMap();

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-comment-translation-toggle]');
    if (!button) return;

    const control = button.closest('[data-comment-translation]');
    const status = control?.querySelector('[data-comment-translation-status]');
    const comment = document.getElementById(button.getAttribute('aria-controls'));
    if (!control || !status || !comment) return;

    const state = translationStates.get(control) ?? {
        original: comment.textContent,
        translated: null,
        showingTranslation: false,
    };
    translationStates.set(control, state);

    const setStatus = (message = '', isError = false) => {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
        status.hidden = message === '';
    };

    if (state.showingTranslation) {
        comment.textContent = state.original;
        state.showingTranslation = false;
        button.textContent = window.idolRankingT('translate');
        button.setAttribute('aria-expanded', 'false');
        setStatus();
        return;
    }

    if (state.translated !== null) {
        comment.textContent = state.translated;
        state.showingTranslation = true;
        button.textContent = window.idolRankingT('show_original');
        button.setAttribute('aria-expanded', 'true');
        setStatus();
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
        if (!response.ok || !result.success || typeof result.data?.content !== 'string') {
            const fallback = result.code === 'translation_unavailable'
                ? 'translation_unavailable'
                : 'translation_error';
            setStatus(result.message || window.idolRankingT(fallback), true);
            return;
        }

        const translated = result.data.content;
        state.translated = translated;
        comment.textContent = translated;
        state.showingTranslation = true;
        button.textContent = window.idolRankingT('show_original');
        button.setAttribute('aria-expanded', 'true');
        setStatus();
    } catch (error) {
        setStatus(window.idolRankingT('translation_error'), true);
    } finally {
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
});
