const toggle = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#main-navigation');
const overlay = document.querySelector('#navigation-overlay');
const closeButton = navigation?.querySelector('.drawer-close');
const drawerLogoutDialog = document.querySelector('#drawer-logout-dialog');
const drawerLogoutTrigger = navigation?.querySelector('[data-drawer-logout-open]');
const searchDialog = document.querySelector('#site-search-dialog');
const languageSelector = document.querySelector('[data-language-selector]');
const mobileLanguageSlot = document.querySelector('[data-mobile-language-selector-slot]');
const desktopLanguageSlot = document.querySelector('[data-desktop-language-selector-slot]');

if (languageSelector && mobileLanguageSlot && desktopLanguageSlot) {
    const placeLanguageSelector = () => {
        if (window.matchMedia('(max-width: 639px)').matches) {
            mobileLanguageSlot.append(languageSelector);
        } else {
            desktopLanguageSlot.append(languageSelector);
        }
    };

    placeLanguageSelector();
    window.matchMedia('(max-width: 639px)').addEventListener('change', placeLanguageSelector);
}

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
        if (event.target.closest('[data-drawer-logout-open], [data-search-open]')) return;
        if (mobile.matches && event.target.closest('a, button')) setOpen(false);
    });
    overlay.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (drawerLogoutDialog?.open || searchDialog?.open) return;
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

if (drawerLogoutDialog && drawerLogoutTrigger) {
    const cancelButton = drawerLogoutDialog.querySelector('[data-drawer-logout-cancel]');
    drawerLogoutTrigger.addEventListener('click', () => {
        drawerLogoutDialog.showModal();
        cancelButton.focus();
    });
    cancelButton.addEventListener('click', () => drawerLogoutDialog.close());
    drawerLogoutDialog.addEventListener('click', (event) => {
        if (event.target === drawerLogoutDialog) drawerLogoutDialog.close();
    });
    drawerLogoutDialog.addEventListener('close', () => {
        if (navigation?.classList.contains('is-open')) drawerLogoutTrigger.focus();
    });
}

const inviteTriggers = document.querySelectorAll('[data-invite-friends-open]');
const inviteDialog = document.querySelector('#invite-friends-dialog');
const inviteIntentKey = 'idolranking_invite_friends_intent';
let inviteReturnFocus = document.querySelector('[data-header-account] .header-account-trigger')
    || document.querySelector('[data-invite-friends-open]');

inviteTriggers.forEach((trigger) => {
    trigger.addEventListener('click', () => {
        if (trigger.dataset.signedIn !== 'true') {
            sessionStorage.setItem(inviteIntentKey, 'true');
            return;
        }

        inviteReturnFocus = trigger;
        inviteDialog?.showModal();
        document.documentElement.classList.add('invite-friends-open');
    });
});

if (inviteDialog) {
    const referralInput = inviteDialog.querySelector('[data-referral-url]');
    const copyStatus = inviteDialog.querySelector('[data-referral-copy-status]');
    const copyButton = inviteDialog.querySelector('[data-referral-copy]');
    const copyIcon = copyButton.querySelector('[data-referral-copy-icon]');
    const copiedLabel = copyButton.querySelector('[data-referral-copied]');
    let copyResetTimer;
    const closeInvite = () => inviteDialog.close();
    const showCopiedState = () => {
        window.clearTimeout(copyResetTimer);
        copyIcon.hidden = true;
        copiedLabel.hidden = false;
        copyButton.setAttribute('aria-label', 'Invite link copied');
        copyResetTimer = window.setTimeout(() => {
            copyIcon.hidden = false;
            copiedLabel.hidden = true;
            copyButton.setAttribute('aria-label', 'Copy invite link');
        }, 1800);
    };

    inviteDialog.querySelector('[data-invite-friends-close]').addEventListener('click', closeInvite);
    inviteDialog.addEventListener('click', (event) => {
        if (event.target === inviteDialog) closeInvite();
    });
    inviteDialog.addEventListener('close', () => {
        document.documentElement.classList.remove('invite-friends-open');
        inviteReturnFocus?.focus();
    });
    copyButton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(referralInput.value);
            copyStatus.textContent = window.idolRankingT('invite_copied');
        } catch {
            referralInput.select();
            document.execCommand('copy');
            copyStatus.textContent = window.idolRankingT('invite_copied');
        }
        copyStatus.hidden = false;
        showCopiedState();
    });
    inviteDialog.querySelector('[data-invite-share="kakao"]').addEventListener('click', async () => {
        if (navigator.share) {
            await navigator.share({ title: window.idolRankingT('invite_friends'), url: referralInput.value }).catch(() => {});
        } else {
            await navigator.clipboard.writeText(referralInput.value).catch(() => {});
            copyStatus.textContent = window.idolRankingT('invite_kakao_copied');
            copyStatus.hidden = false;
        }
    });

    if (sessionStorage.getItem(inviteIntentKey) === 'true') {
        sessionStorage.removeItem(inviteIntentKey);
        inviteDialog.showModal();
        document.documentElement.classList.add('invite-friends-open');
    }
}

const headerAccount = document.querySelector('[data-header-account]');
const accountTrigger = headerAccount?.querySelector('.header-account-trigger');
const accountMenu = headerAccount?.querySelector('.header-account-menu');

if (headerAccount && accountTrigger && accountMenu) {
    const desktop = window.matchMedia('(min-width: 640px)');
    const setAccountOpen = (open, restoreFocus = false) => {
        const wasOpen = accountTrigger.getAttribute('aria-expanded') === 'true';
        open = open && desktop.matches;
        accountTrigger.setAttribute('aria-expanded', String(open));
        accountMenu.hidden = !open;
        if (!open && wasOpen && restoreFocus) accountTrigger.focus();
    };

    accountTrigger.addEventListener('click', () => {
        setAccountOpen(accountTrigger.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('click', (event) => {
        if (accountTrigger.getAttribute('aria-expanded') === 'true' && !headerAccount.contains(event.target)) {
            setAccountOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && accountTrigger.getAttribute('aria-expanded') === 'true') {
            event.preventDefault();
            setAccountOpen(false, true);
        }
    });

    desktop.addEventListener('change', () => setAccountOpen(false));
    setAccountOpen(false);
}

const guestAccount = document.querySelector('[data-guest-account]');
const guestAccountTrigger = guestAccount?.querySelector('[data-guest-account-trigger]');
const guestAccountMenu = guestAccount?.querySelector('.guest-account-menu');

if (guestAccount && guestAccountTrigger && guestAccountMenu) {
    const desktop = window.matchMedia('(min-width: 640px)');
    const setGuestAccountOpen = (open, restoreFocus = false) => {
        const wasOpen = guestAccountTrigger.getAttribute('aria-expanded') === 'true';
        open = open && desktop.matches;
        guestAccountTrigger.setAttribute('aria-expanded', String(open));
        guestAccountMenu.hidden = !open;
        if (!open && wasOpen && restoreFocus) guestAccountTrigger.focus();
    };

    guestAccountTrigger.addEventListener('click', () => {
        setGuestAccountOpen(guestAccountTrigger.getAttribute('aria-expanded') !== 'true');
    });
    guestAccountMenu.addEventListener('click', (event) => {
        if (event.target.closest('[data-sign-in-open]')) setGuestAccountOpen(false);
    });
    document.addEventListener('click', (event) => {
        if (guestAccountTrigger.getAttribute('aria-expanded') === 'true' && !guestAccount.contains(event.target)) {
            setGuestAccountOpen(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && guestAccountTrigger.getAttribute('aria-expanded') === 'true') {
            event.preventDefault();
            setGuestAccountOpen(false, true);
        }
    });
    desktop.addEventListener('change', () => setGuestAccountOpen(false));
    setGuestAccountOpen(false);
}
