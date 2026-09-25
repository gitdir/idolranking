const toggle = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#main-navigation');
const overlay = document.querySelector('#navigation-overlay');
const closeButton = navigation?.querySelector('.drawer-close');

if (toggle && navigation && overlay && closeButton) {
    const mobile = window.matchMedia('(max-width: 639px)');
    const setOpen = (open, restoreFocus = true) => {
        open = open && mobile.matches;
        const wasOpen = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(open));
        navigation.classList.toggle('is-open', open);
        navigation.inert = mobile.matches && !open;
        overlay.hidden = !open;
        document.documentElement.classList.toggle('drawer-open', open);
        if (open) {
            closeButton.focus();
        } else if (wasOpen && restoreFocus) {
            toggle.focus();
        }
    };

    toggle.addEventListener('click', () => {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    navigation.addEventListener('click', (event) => {
        if (mobile.matches && event.target.closest('a, button')) setOpen(false);
    });
    overlay.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (toggle.getAttribute('aria-expanded') !== 'true') return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
        } else if (event.key === 'Tab') {
            const items = [...navigation.querySelectorAll('a[href], button:not([disabled])')]
                .filter((item) => item.getClientRects().length > 0);
            const index = items.indexOf(document.activeElement);
            const next = event.shiftKey
                ? (index <= 0 ? items.length - 1 : index - 1)
                : (index + 1) % items.length;
            event.preventDefault();
            items[next].focus();
        }
    });

    mobile.addEventListener('change', () => {
        const focusWasInside = navigation.contains(document.activeElement);
        setOpen(false, mobile.matches);
        if (focusWasInside) {
            (mobile.matches ? toggle : navigation.querySelector('a')).focus();
        }
    });
    setOpen(false, false);
}
