const reportButtons = document.querySelectorAll('[data-fan-post-report-open], [data-comment-report-open]');
const reportModal = document.querySelector('#fan-post-report-modal');

if (reportButtons.length && reportModal) {
    const form = reportModal.querySelector('[data-fan-post-report-form]');
    const details = form.querySelector('[name="details"]');
    const error = form.querySelector('[data-fan-post-report-error]');
    const submit = form.querySelector('[type="submit"]');
    const success = document.querySelector('[data-fan-post-report-success]');
    const title = reportModal.querySelector('#fan-post-report-title');
    let activeButton = reportButtons[0];
    let returnFocus = activeButton;

    const selectedReason = () => form.querySelector('[name="reason"]:checked')?.value;
    const updateDetailsRequirement = () => {
        details.required = selectedReason() === 'other';
    };
    const close = () => reportModal.close();

    reportButtons.forEach((button) => button.addEventListener('click', () => {
        if (button.dataset.signedIn !== 'true') {
            document.dispatchEvent(new CustomEvent('idolranking:sign-in'));
            return;
        }
        activeButton = button;
        returnFocus = button;
        form.action = button.dataset.endpoint;
        title.textContent = button.dataset.reportTitle;
        error.hidden = true;
        reportModal.showModal();
        document.documentElement.classList.add('fan-post-report-open');
        form.querySelector('[name="reason"]').focus();
    }));
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
                error.textContent = result.message ?? Object.values(result.errors ?? {})[0]?.[0] ?? window.idolRankingT('report_failed');
                error.hidden = false;
                return;
            }
            form.reset();
            updateDetailsRequirement();
            close();
            success.textContent = result.message;
            success.hidden = false;
            activeButton.disabled = true;
        } catch (requestError) {
            error.textContent = window.idolRankingT('report_try_again');
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });
}
