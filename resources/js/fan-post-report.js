const reportButton = document.querySelector('[data-fan-post-report-open]');
const reportModal = document.querySelector('#fan-post-report-modal');

if (reportButton && reportModal) {
    const form = reportModal.querySelector('[data-fan-post-report-form]');
    const details = form.querySelector('[name="details"]');
    const error = form.querySelector('[data-fan-post-report-error]');
    const submit = form.querySelector('[type="submit"]');
    const success = document.querySelector('[data-fan-post-report-success]');
    let returnFocus = reportButton;

    const selectedReason = () => form.querySelector('[name="reason"]:checked')?.value;
    const updateDetailsRequirement = () => {
        details.required = selectedReason() === 'other';
    };
    const close = () => reportModal.close();

    reportButton.addEventListener('click', () => {
        if (reportButton.dataset.signedIn !== 'true') {
            document.dispatchEvent(new CustomEvent('idolranking:sign-in'));
            return;
        }
        returnFocus = reportButton;
        error.hidden = true;
        reportModal.showModal();
        document.documentElement.classList.add('fan-post-report-open');
        form.querySelector('[name="reason"]').focus();
    });
    form.addEventListener('change', updateDetailsRequirement);
    reportModal.querySelectorAll('[data-fan-post-report-cancel]').forEach((button) => {
        button.addEventListener('click', close);
    });
    reportModal.addEventListener('close', () => {
        document.documentElement.classList.remove('fan-post-report-open');
        returnFocus?.focus();
    });
    reportModal.addEventListener('click', (event) => {
        if (event.target === reportModal) close();
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
                error.textContent = result.message ?? Object.values(result.errors ?? {})[0]?.[0] ?? 'Unable to submit the report.';
                error.hidden = false;
                return;
            }
            form.reset();
            updateDetailsRequirement();
            close();
            success.textContent = result.message;
            success.hidden = false;
            reportButton.disabled = true;
        } catch (requestError) {
            error.textContent = 'Unable to submit the report. Please try again.';
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });
}
