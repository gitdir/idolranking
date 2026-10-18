const modal = document.querySelector('#sign-in-modal');

if (modal) {
    const password = modal.querySelector('#sign-in-password');
    const visibility = modal.querySelector('.sign-in-visibility');
    const form = modal.querySelector('form');
    const submit = form.querySelector('.sign-in-submit');
    const error = form.querySelector('[data-sign-in-error]');
    const returnUrlInput = form.querySelector('[name="return_url"]');
    const defaultReturnUrl = returnUrlInput.value;
    const googleLink = modal.querySelector('.sign-in-google');
    const defaultGoogleHref = googleLink.href;
    const setPasswordVisible = (visible) => {
        password.type = visible ? 'text' : 'password';
        visibility.setAttribute('aria-pressed', String(visible));
        visibility.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    };
    visibility.addEventListener('click', () => setPasswordVisible(password.type === 'password'));
    let returnFocus;
    const open = (trigger = document.activeElement) => {
        if (modal.open) return;
        const drawer = document.querySelector('#main-navigation');
        const guestAccount = trigger instanceof Element ? trigger.closest('[data-guest-account]') : null;
        const fromDrawer = trigger instanceof Element && drawer?.contains(trigger)
            && window.matchMedia('(max-width: 639px)').matches;
        returnFocus = guestAccount?.querySelector('[data-guest-account-trigger]')
            || (fromDrawer ? document.querySelector('.menu-toggle') : trigger);
        const requestedReturnUrl = trigger instanceof Element ? trigger.dataset.signInReturnUrl : null;
        returnUrlInput.value = requestedReturnUrl || defaultReturnUrl;
        if (requestedReturnUrl) {
            const googleUrl = new URL(defaultGoogleHref, window.location.href);
            googleUrl.searchParams.set('return_url', requestedReturnUrl);
            googleLink.href = googleUrl.toString();
        }
        if (drawer?.classList.contains('is-open')) drawer.querySelector('.drawer-close').click();
        modal.showModal();
        document.documentElement.classList.add('sign-in-open');
        modal.querySelector('#sign-in-email').focus();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-sign-in-open]');
        if (trigger) {
            event.preventDefault();
            open(trigger);
        }
    });
    // Any page can open the same modal without adding another instance.
    document.addEventListener('idolranking:sign-in', () => open());
    modal.querySelector('[data-sign-in-close]').addEventListener('click', () => modal.close());
    let backdropPointerDown = false;
    const outside = (event) => {
        const rect = modal.getBoundingClientRect();
        return event.target === modal && (event.clientX < rect.left || event.clientX > rect.right
            || event.clientY < rect.top || event.clientY > rect.bottom);
    };
    modal.addEventListener('pointerdown', (event) => { backdropPointerDown = outside(event); });
    modal.addEventListener('click', (event) => {
        if (backdropPointerDown && outside(event)) modal.close();
        backdropPointerDown = false;
    });
    // Native dialog provides Escape closing, focus containment and background inertness.
    modal.addEventListener('close', () => {
        document.documentElement.classList.remove('sign-in-open');
        form.reset();
        error.hidden = true;
        error.textContent = '';
        setPasswordVisible(false);
        returnUrlInput.value = defaultReturnUrl;
        googleLink.href = defaultGoogleHref;
        returnFocus?.focus();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.hidden = true;
        error.textContent = '';
        submit.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const result = await response.json();
            if (!response.ok) {
                error.textContent = result.message || window.idolRankingT('sign_in_failed');
                error.hidden = false;
                return;
            }

            modal.close();
            window.location.assign(result.redirect || window.location.href);
        } catch {
            error.textContent = window.idolRankingT('sign_in_try_again');
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });

    if (modal.dataset.autoOpen === 'true') open();
}
