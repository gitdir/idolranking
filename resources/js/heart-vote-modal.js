const modal = document.querySelector('#heart-vote-modal');

if (modal) {
    const form = modal.querySelector('.heart-vote-form');
    const input = form.elements.heart_amount;
    const presets = [...modal.querySelectorAll('[data-heart-preset]')];
    const submit = modal.querySelector('.heart-vote-submit');
    const submitLabel = modal.querySelector('[data-heart-vote-submit-label]');
    const error = modal.querySelector('#heart-vote-error');
    const voteImage = modal.querySelector('[data-heart-vote-image]');
    const defaultVoteImage = voteImage.src;
    const formatter = new Intl.NumberFormat('en-US');
    const randomDummyMode = modal.dataset.randomDummyMode === 'true';
    let returnFocus;
    let target = {};
    let requestKey = '';
    let isSubmitting = false;

    const newRequestKey = () => {
        if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        return [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
    };

    const integerValue = () => {
        const raw = input.value.trim();
        if (!/^\d+$/.test(raw)) return null;
        const value = Number(raw);
        return Number.isSafeInteger(value) ? value : null;
    };
    const validate = () => {
        const value = integerValue();
        const maximum = Number(modal.dataset.myHearts);
        let message = '';
        if (value === null || value < 1) message = window.idolRankingT('vote_integer');
        else if (!randomDummyMode && value > maximum) message = window.idolRankingT('vote_max', { amount: formatter.format(maximum) });
        error.textContent = message;
        error.hidden = !message;
        input.toggleAttribute('aria-invalid', Boolean(message));
        submit.disabled = Boolean(message);
        presets.forEach((button) => button.classList.toggle('is-active', value === Number(button.dataset.heartPreset)));
        const amount = value && value > 0 ? formatter.format(value) : '0';
        submitLabel.textContent = window.idolRankingT(value === 1 ? 'vote_label_one' : 'vote_label', { amount });
        return !message;
    };
    const setAmount = (amount) => {
        input.value = String(amount);
        validate();
    };
    const number = (value) => {
        const parsed = Number.parseInt(value, 10);
        return Number.isSafeInteger(parsed) && parsed >= 0 ? parsed : 0;
    };
    const open = (trigger) => {
        target = {
            idx: trigger.dataset.voteTargetId,
            name: trigger.dataset.voteName || 'Idol',
            meta: trigger.dataset.voteMeta || '',
            month: trigger.dataset.voteMonth || '',
        };
        const maximum = number(modal.dataset.myHearts);
        requestKey = newRequestKey();
        voteImage.src = trigger.dataset.voteImage || defaultVoteImage;
        voteImage.alt = `${target.name} placeholder`;
        modal.querySelector('[data-heart-vote-name]').textContent = target.name;
        modal.querySelector('[data-heart-vote-meta]').textContent = target.meta;
        modal.querySelector('[data-heart-vote-total]').textContent = formatter.format(number(trigger.dataset.voteTotal));
        modal.querySelector('[data-heart-vote-posts]').textContent = formatter.format(number(trigger.dataset.votePosts));
        if (!randomDummyMode) {
            modal.querySelector('[data-heart-vote-balance]').textContent = formatter.format(maximum);
            input.max = String(maximum);
            presets.forEach((button) => { button.disabled = Number(button.dataset.heartPreset) > maximum; });
            setAmount(maximum >= 1 ? 1 : 0);
        } else {
            input.removeAttribute('max');
            presets.forEach((button) => { button.disabled = false; });
            setAmount(1);
        }
        returnFocus = trigger;
        modal.showModal();
        document.documentElement.classList.add('heart-vote-open');
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-heart-vote-open]');
        if (!trigger) return;
        event.preventDefault();
        if (modal.dataset.signedIn !== 'true') {
            document.dispatchEvent(new CustomEvent('idolranking:sign-in'));
            return;
        }
        open(trigger);
    });
    presets.forEach((button) => button.addEventListener('click', () => setAmount(Number(button.dataset.heartPreset))));
    input.addEventListener('input', validate);
    modal.querySelectorAll('[data-heart-vote-close]').forEach((button) => button.addEventListener('click', () => {
        if (!isSubmitting) modal.close();
    }));
    let backdropPointerDown = false;
    const outside = (event) => {
        const rect = modal.getBoundingClientRect();
        return event.target === modal && (event.clientX < rect.left || event.clientX > rect.right
            || event.clientY < rect.top || event.clientY > rect.bottom);
    };
    modal.addEventListener('pointerdown', (event) => { backdropPointerDown = outside(event); });
    modal.addEventListener('click', (event) => {
        if (!isSubmitting && backdropPointerDown && outside(event)) modal.close();
        backdropPointerDown = false;
    });
    modal.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || isSubmitting) return;
        event.preventDefault();
        modal.close();
    });
    modal.addEventListener('close', () => {
        document.documentElement.classList.remove('heart-vote-open');
        form.reset();
        error.hidden = true;
        input.removeAttribute('aria-invalid');
        target = {};
        returnFocus?.focus();
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (isSubmitting) return;
        if (!validate()) {
            input.focus();
            return;
        }
        isSubmitting = true;
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        error.hidden = true;

        fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.elements._token.value,
            },
            body: JSON.stringify({
                idol_idx: target.idx,
                heart_amount: integerValue(),
                request_key: requestKey,
            }),
        }).then(async (response) => {
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.success) {
                const validationMessage = payload.errors?.heart_amount?.[0];
                throw new Error(validationMessage || payload.message || window.idolRankingT('vote_failed'));
            }

            const balance = number(payload.data?.heart_balance);
            const monthlyTotal = number(payload.data?.monthly_vote_total);
            if (!randomDummyMode) {
                modal.dataset.myHearts = String(balance);
                modal.querySelector('[data-heart-vote-balance]').textContent = formatter.format(balance);
                document.querySelectorAll('[data-member-heart-balance]').forEach((element) => {
                    element.textContent = formatter.format(balance);
                });
            }
            if (target.month === payload.data?.vote_month) {
                document.querySelectorAll('[data-heart-vote-open]').forEach((trigger) => {
                    if (trigger.dataset.voteTargetId === String(target.idx)) {
                        trigger.dataset.voteTotal = String(monthlyTotal);
                    }
                });
                document.querySelectorAll('[data-vote-total-display]').forEach((element) => {
                    if (element.dataset.voteTotalDisplay === String(target.idx)) {
                        element.textContent = formatter.format(monthlyTotal);
                    }
                });
            }

            document.dispatchEvent(new CustomEvent('idolranking:heart-vote-success', {
                detail: payload.data,
            }));
            document.dispatchEvent(new CustomEvent('idolranking:notify', {
                detail: {
                    type: 'success',
                    title: window.idolRankingT('vote_complete'),
                    message: window.idolRankingT(number(payload.data?.heart_amount) === 1 ? 'vote_success_one' : 'vote_success', {
                        amount: formatter.format(number(payload.data?.heart_amount)), name: payload.data?.target_name || target.name,
                    }),
                    detail: randomDummyMode ? '' : window.idolRankingT('remaining_hearts', { amount: formatter.format(balance) }),
                    duration: 2600,
                },
            }));
            requestKey = newRequestKey();
            isSubmitting = false;
            form.removeAttribute('aria-busy');
            modal.close();
        }).catch((exception) => {
            error.textContent = exception instanceof TypeError
                ? window.idolRankingT('vote_failed')
                : (exception.message || window.idolRankingT('vote_failed'));
            error.hidden = false;
            isSubmitting = false;
            form.removeAttribute('aria-busy');
            submit.disabled = false;
        });
    });
}
