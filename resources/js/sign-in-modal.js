const modal = document.querySelector('#sign-in-modal');

if (modal) {
    const password = modal.querySelector('#sign-in-password');
    const visibility = modal.querySelector('.sign-in-visibility');
    const form = modal.querySelector('form');
    const submit = form.querySelector('.sign-in-submit');
    const error = form.querySelector('[data-sign-in-error]');
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
        const fromDrawer = trigger instanceof Element && drawer?.contains(trigger)
            && window.matchMedia('(max-width: 639px)').matches;
        returnFocus = fromDrawer ? document.querySelector('.menu-toggle') : trigger;
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
                error.textContent = result.message || 'Unable to sign in.';
                error.hidden = false;
                return;
            }

            modal.close();
            window.location.assign(result.redirect || window.location.href);
        } catch {
            error.textContent = 'Unable to sign in. Please try again.';
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });

    if (modal.dataset.autoOpen === 'true') open();
}
