const registerForm = document.querySelector('.register-form');

if (registerForm) {
    const submit = registerForm.querySelector('.register-submit');
    const message = registerForm.querySelector('[data-register-message]');
    const emailInput = registerForm.querySelector('#register-email');
    const passwordInput = registerForm.querySelector('#register-password');
    const passwordConfirmationInput = registerForm.querySelector('#register-confirm-password');
    const nicknameInput = registerForm.querySelector('#register-nickname');
    const codeInput = registerForm.querySelector('#register-verification-code');
    const verificationPanel = registerForm.querySelector('[data-verification-panel]');
    const verificationStatus = registerForm.querySelector('[data-verification-status]');
    const verificationTimer = registerForm.querySelector('[data-verification-timer]');
    const sendButton = registerForm.querySelector('[data-send-verification-code]');
    const verifyButton = registerForm.querySelector('[data-verify-email]');
    const csrfToken = registerForm.querySelector('input[name="_token"]').value;

    let verificationEmail = registerForm.dataset.verificationEmail || '';
    let verified = registerForm.dataset.verificationVerified === '1';
    let expiresAt = Number(registerForm.dataset.verificationExpiresAt || 0);
    let resendAt = Number(registerForm.dataset.verificationResendAt || 0);
    let timerId = null;
    let resetPromise = Promise.resolve();

    const normalizeEmail = (value) => value.trim().toLowerCase();
    const formatTime = (seconds) => `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    const errorFor = (field) => registerForm.querySelector(`[data-register-error="${field}"]`);

    const clearErrors = () => {
        message.hidden = true;
        message.textContent = '';
        registerForm.querySelectorAll('[data-register-error]').forEach((error) => {
            error.hidden = true;
            error.textContent = '';
        });
    };

    const showError = (field, text) => {
        const target = errorFor(field);
        if (target) {
            target.textContent = text;
            target.hidden = false;
        }
    };

    const validateRegistrationFields = () => {
        let firstInvalid = null;
        const invalidate = (field, text, input) => {
            showError(field, text);
            firstInvalid ||= input;
        };
        const passwordLength = Array.from(passwordInput.value).length;
        const nicknameLength = Array.from(nicknameInput.value.trim()).length;

        if (!emailInput.validity.valid) {
            invalidate('email', 'Enter a valid email address.', emailInput);
        } else if (!verified || normalizeEmail(emailInput.value) !== verificationEmail) {
            invalidate('email_verification', 'Verify this email address before continuing.', emailInput);
        }

        if (passwordLength < 8 || passwordLength > 64) {
            invalidate('password', 'Password must be between 8 and 64 characters.', passwordInput);
        } else if (!/[A-Za-z]/.test(passwordInput.value) || !/[0-9]/.test(passwordInput.value)) {
            invalidate('password', 'Password must contain at least one letter and one number.', passwordInput);
        }

        if (passwordConfirmationInput.value !== passwordInput.value) {
            invalidate('password_confirmation', 'Passwords do not match.', passwordConfirmationInput);
        }

        if (nicknameLength < 2 || nicknameLength > 20) {
            invalidate('nickname', 'Nickname must be between 2 and 20 characters.', nicknameInput);
        }

        firstInvalid?.focus();
        return firstInvalid === null;
    };

    const postVerification = async (url, values) => {
        const body = new FormData();
        body.append('_token', csrfToken);
        Object.entries(values).forEach(([key, value]) => body.append(key, value));
        const response = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
        const result = await response.json();
        return { response, result };
    };

    const updateVerificationUI = () => {
        const now = Math.floor(Date.now() / 1000);
        const resendRemaining = Math.max(0, resendAt - now);
        const expiresRemaining = Math.max(0, expiresAt - now);

        verificationPanel.hidden = !verificationEmail;
        verificationStatus.hidden = !verified;
        codeInput.disabled = verified;
        verifyButton.disabled = verified;

        if (verified) {
            verificationStatus.textContent = 'Email verified.';
            verificationTimer.textContent = '';
            sendButton.textContent = 'Verified';
            sendButton.disabled = true;
            return;
        }

        sendButton.disabled = resendRemaining > 0;
        sendButton.textContent = resendRemaining > 0 ? `Resend in ${resendRemaining}s` : (verificationEmail ? 'Resend Code' : 'Send Code');
        verificationTimer.textContent = verificationEmail
            ? (expiresRemaining > 0 ? `Code expires in ${formatTime(expiresRemaining)}` : 'Code expired. Request a new code.')
            : '';
    };

    const startTimer = () => {
        window.clearInterval(timerId);
        updateVerificationUI();
        timerId = window.setInterval(updateVerificationUI, 1000);
    };

    const invalidateVerification = () => {
        if (!verificationEmail) return;
        verificationEmail = '';
        verified = false;
        expiresAt = 0;
        resendAt = 0;
        codeInput.value = '';
        verificationPanel.hidden = true;
        updateVerificationUI();
        resetPromise = postVerification(registerForm.dataset.verificationResetUrl, {}).catch(() => {});
    };

    emailInput.addEventListener('input', () => {
        if (verificationEmail && normalizeEmail(emailInput.value) !== verificationEmail) {
            invalidateVerification();
        }
    });

    sendButton.addEventListener('click', async () => {
        clearErrors();
        sendButton.disabled = true;
        try {
            await resetPromise;
            const { response, result } = await postVerification(registerForm.dataset.verificationSendUrl, {
                email: emailInput.value,
            });
            if (!response.ok) {
                showError('email', result.errors?.email?.[0] || result.message || 'Unable to send the verification code.');
                if (result.retry_after) resendAt = Math.floor(Date.now() / 1000) + Number(result.retry_after);
                return;
            }

            verificationEmail = result.email;
            emailInput.value = result.email;
            verified = false;
            expiresAt = Number(result.expires_at);
            resendAt = Number(result.resend_available_at);
            codeInput.value = '';
            verificationPanel.hidden = false;
            verificationStatus.hidden = true;
            codeInput.focus();
        } catch {
            showError('email', 'Unable to send the verification code. Please try again.');
        } finally {
            updateVerificationUI();
        }
    });

    verifyButton.addEventListener('click', async () => {
        clearErrors();
        verifyButton.disabled = true;
        try {
            const { response, result } = await postVerification(registerForm.dataset.verificationVerifyUrl, {
                email: emailInput.value,
                code: codeInput.value,
            });
            if (!response.ok) {
                showError('email_verification', result.errors?.code?.[0] || result.message || 'Unable to verify this code.');
                return;
            }

            verificationEmail = result.email;
            verified = true;
            verificationStatus.textContent = result.message;
            verificationStatus.hidden = false;
        } catch {
            showError('email_verification', 'Unable to verify this code. Please try again.');
        } finally {
            updateVerificationUI();
        }
    });

    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();

        if (!validateRegistrationFields()) {
            message.textContent = 'Please check your registration details.';
            message.hidden = false;
            return;
        }

        submit.disabled = true;

        try {
            const response = await fetch(registerForm.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(registerForm),
            });
            const result = await response.json();
            if (!response.ok) {
                Object.entries(result.errors || {}).forEach(([field, errors]) => showError(field, errors[0]));
                message.textContent = result.message || 'Unable to create your account.';
                message.hidden = false;
                return;
            }

            window.location.assign(result.redirect);
        } catch {
            message.textContent = 'Unable to create your account. Please try again.';
            message.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });

    startTimer();
}

document.querySelectorAll('.register-visibility').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${input.id.includes('confirm') ? 'confirm password' : 'password'}`);
    });
});
