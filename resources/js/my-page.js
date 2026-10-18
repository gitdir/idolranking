import { initSquareImageCrop } from './square-image-crop';

const profileForm = document.querySelector('.my-profile-form');
if (profileForm) {
    const bio = profileForm.querySelector('#profile-bio');
    const count = profileForm.querySelector('output');
    const updateCount = () => { count.textContent = `${Array.from(bio.value).length}/160`; };
    bio.addEventListener('input', updateCount);
    updateCount();

    const imageInput = document.querySelector('#my-profile-image');
    const preview = document.querySelector('.my-profile-avatar [data-image-preview]');
    const selection = document.querySelector('.my-profile-image-actions[data-image-selection]');
    let savedImageUrl = preview.src;
    const crop = initSquareImageCrop({
        imageInput,
        selection,
        preview,
        dialog: document.querySelector('#my-profile-crop-dialog'),
        openButton: selection.querySelector('[data-crop-open]'),
        invalidMessage: 'Choose one JPG, PNG, or GIF image up to 5MB.',
        outputName: 'member-profile.png',
        onClear: () => { preview.src = savedImageUrl; },
    });
    document.querySelectorAll('[data-profile-image-select]').forEach((button) => {
        button.addEventListener('click', () => imageInput.click());
    });
    selection.querySelector('[data-image-remove]').addEventListener('click', crop.clear);

    const nicknameInput = profileForm.querySelector('#profile-nickname');
    const nicknameCheck = profileForm.querySelector('[data-nickname-check]');
    const nicknameResult = profileForm.querySelector('[data-nickname-check-result]');
    let checkedNickname = null;
    nicknameInput.addEventListener('input', () => {
        if (nicknameInput.value.trim() !== checkedNickname) {
            checkedNickname = null;
            nicknameResult.hidden = true;
            nicknameResult.classList.remove('is-available', 'is-error');
        }
    });
    nicknameCheck.addEventListener('click', async () => {
        const requestedNickname = nicknameInput.value;
        nicknameCheck.disabled = true;
        nicknameResult.hidden = true;
        try {
            const body = new FormData();
            body.append('_token', profileForm.querySelector('[name="_token"]').value);
            body.append('nickname', nicknameInput.value);
            const response = await fetch(nicknameCheck.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            const data = await response.json();
            if (nicknameInput.value !== requestedNickname) {
                checkedNickname = null;
                nicknameResult.hidden = true;
                return;
            }
            nicknameResult.textContent = data.message || window.idolRankingT('nickname_check_failed');
            nicknameResult.classList.toggle('is-available', response.ok && data.available === true);
            nicknameResult.classList.toggle('is-error', !response.ok || data.available !== true);
            nicknameResult.hidden = false;
            checkedNickname = response.ok && data.available === true ? data.nickname : null;
            if (checkedNickname !== null) nicknameInput.value = checkedNickname;
        } catch {
            checkedNickname = null;
            nicknameResult.textContent = window.idolRankingT('nickname_check_retry');
            nicknameResult.classList.remove('is-available');
            nicknameResult.classList.add('is-error');
            nicknameResult.hidden = false;
        } finally {
            nicknameCheck.disabled = false;
        }
    });

    const status = document.querySelector('#profile-update-status');
    const submit = profileForm.querySelector('button[type="submit"]');
    let profilePending = false;
    profileForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (profilePending) return;
        profilePending = true;
        submit.disabled = true;
        status.hidden = true;
        try {
            const response = await fetch(profileForm.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(profileForm),
            });
            const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
            status.textContent = response.ok && data.success
                ? data.message
                : data.message || 'Unable to update your profile. Please check the form and try again.';
            status.hidden = false;
            if (response.ok && data.success) {
                checkedNickname = nicknameInput.value.trim();
                if (data.data?.profile_image_url) {
                    savedImageUrl = data.data.profile_image_url;
                    preview.src = savedImageUrl;
                    document.querySelectorAll('.drawer-member-avatar, .header-account-trigger-avatar, .header-account-summary-avatar img')
                        .forEach((image) => { image.src = savedImageUrl; });
                }
                selection.hidden = true;
                imageInput.value = '';
            }
        } catch {
                status.textContent = window.idolRankingT('network_error');
            status.hidden = false;
        } finally {
            profilePending = false;
            submit.disabled = false;
        }
    });
}

const myInviteCopy = document.querySelector('[data-my-invite-copy]');
if (myInviteCopy) {
    myInviteCopy.addEventListener('click', async () => {
        const inviteUrl = document.querySelector('#my-invite-url');
        const status = document.querySelector('[data-my-invite-copy-status]');
        try {
            await navigator.clipboard.writeText(inviteUrl.value);
        } catch {
            inviteUrl.select();
            document.execCommand('copy');
        }
        status.textContent = window.idolRankingT('invite_copied');
        status.hidden = false;
    });
}

const passwordModal = document.querySelector('#change-password-modal');
if (passwordModal) {
    const panel = passwordModal.querySelector('.change-password-panel');
    const form = passwordModal.querySelector('form');
    const submit = form.querySelector('[type="submit"]');
    const message = form.querySelector('#change-password-message');
    const success = document.querySelector('#password-change-success');
    const trigger = document.querySelector('[data-change-password-open]');
    let pending = false;
    let generation = 0;
    const clearErrors = () => {
        form.querySelectorAll('.my-password-error').forEach((el) => { el.textContent = ''; el.hidden = true; });
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    };
    trigger.addEventListener('click', () => {
        passwordModal.showModal();
        document.documentElement.classList.add('change-password-open');
        form.elements.current_password.focus();
    });
    passwordModal.querySelectorAll('[data-change-password-close]').forEach((button) => {
        button.addEventListener('click', () => passwordModal.close());
    });
    const outside = (event) => {
        const rect = panel.getBoundingClientRect();
        return event.target === passwordModal && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom);
    };
    let startedOutside = false;
    passwordModal.addEventListener('pointerdown', (event) => { startedOutside = outside(event); });
    passwordModal.addEventListener('click', (event) => {
        if (startedOutside && outside(event)) passwordModal.close();
        startedOutside = false;
    });
    passwordModal.addEventListener('close', () => {
        generation++;
        form.reset();
        clearErrors();
        document.documentElement.classList.remove('change-password-open');
        trigger.focus();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending) return;
        clearErrors();
        success.hidden = true;
        const requestGeneration = generation;
        pending = true;
        submit.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
            if (response.ok && data.success) {
                if (requestGeneration === generation) passwordModal.close();
                success.textContent = data.message;
                success.hidden = false;
                return;
            }
            if (requestGeneration !== generation) return;
            message.textContent = response.status === 419 ? window.idolRankingT('session_expired')
                : response.status === 429 ? 'Too many attempts. Please wait a minute and try again.'
                : response.status >= 500 ? 'Unable to change your password right now. Please try again later.'
                : data.message || window.idolRankingT('please_try_again');
            message.hidden = false;
            for (const [name, errors] of Object.entries(data.errors || {})) {
                const target = form.querySelector(`[data-password-error="${name}"]`);
                if (!target) continue;
                target.textContent = errors.join(' ');
                target.hidden = false;
                form.elements[name]?.setAttribute('aria-invalid', 'true');
            }
            form.querySelector('[aria-invalid]')?.focus();
        } catch {
            if (requestGeneration === generation) {
                message.textContent = window.idolRankingT('network_check');
                message.hidden = false;
            }
        } finally {
            pending = false;
            submit.disabled = false;
        }
    });
}
