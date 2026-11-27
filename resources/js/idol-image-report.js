const buttons = document.querySelectorAll('[data-idol-image-report-open]');
const dialog = document.querySelector('#idol-image-report-modal');

if (buttons.length && dialog) {
    const form = dialog.querySelector('[data-idol-image-report-form]');
    const error = dialog.querySelector('[data-idol-image-report-error]');
    const details = form.querySelector('[name="details"]');
    const submit = form.querySelector('[type="submit"]');
    let activeButton = null;

    const close = () => dialog.close();
    const updateDetailsRequirement = () => {
        details.required = form.querySelector('[name="reason"]:checked')?.value === 'other';
    };

    buttons.forEach((button) => button.addEventListener('click', () => {
        if (button.dataset.signedIn !== 'true') {
            document.dispatchEvent(new CustomEvent('idolranking:sign-in'));
            return;
        }

        activeButton = button;
        form.action = button.dataset.endpoint;
        error.hidden = true;
        dialog.showModal();
        document.documentElement.classList.add('fan-post-report-open');
        form.querySelector('[name="reason"]').focus();
    }));

    form.addEventListener('change', updateDetailsRequirement);
    dialog.querySelectorAll('[data-idol-image-report-cancel]').forEach((button) => button.addEventListener('click', close));
    dialog.addEventListener('close', () => {
        document.documentElement.classList.remove('fan-post-report-open');
        activeButton?.focus();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        updateDetailsRequirement();
        if (!form.reportValidity()) return;

        submit.disabled = true;
        error.hidden = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                },
                body: new FormData(form),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                error.textContent = result.message ?? Object.values(result.errors ?? {})[0]?.[0] ?? window.idolRankingT('report_failed');
                error.hidden = false;
                return;
            }

            form.reset();
            updateDetailsRequirement();
            close();
            const success = document.querySelector('[data-idol-image-report-success]');
            if (success) {
                success.textContent = result.message;
                success.hidden = false;
            }
            if (activeButton) {
                activeButton.disabled = true;
                activeButton.querySelector('[data-idol-image-report-label]').textContent = activeButton.dataset.reportedLabel;
            }
        } catch {
            error.textContent = window.idolRankingT('report_try_again');
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });
}
