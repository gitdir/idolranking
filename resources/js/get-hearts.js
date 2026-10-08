const dialog = document.querySelector('#watch-ad-dialog');

const checkInButton = document.querySelector('[data-daily-check-in]');

if (checkInButton) {
    checkInButton.addEventListener('click', async () => {
        checkInButton.disabled = true;

        try {
            const response = await fetch(checkInButton.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': checkInButton.dataset.csrf,
                },
            });
            const result = await response.json();

            if (result.data) {
                const balance = document.querySelector('[data-get-hearts-balance]');
                const todayEarned = document.querySelector('[data-get-hearts-today-earned]');
                const progress = document.querySelector('[data-daily-check-in-progress]');
                const progressBar = document.querySelector('[data-daily-check-in-progress-bar]');
                if (balance) balance.textContent = Number(result.data.heart_balance).toLocaleString();
                if (todayEarned) todayEarned.textContent = `+ ${Number(result.data.today_earned).toLocaleString()}`;
                if (progress) progress.textContent = `${Number(result.data.progress).toLocaleString()} / ${Number(result.data.daily_limit).toLocaleString()}`;
                if (progressBar) progressBar.style.width = `${Number(result.data.progress_percent)}%`;
            }

            document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: {
                type: response.ok ? 'success' : 'error',
                title: response.ok ? 'Daily Check-in' : 'Unable to check in',
                message: result.message || 'Please try again.',
            }}));

            if (response.ok || result.already_claimed) checkInButton.remove();
            else checkInButton.disabled = false;
        } catch {
            checkInButton.disabled = false;
            document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: {
                type: 'error', title: 'Unable to check in', message: 'Please try again.',
            }}));
        }
    });
}

if (dialog) {
    let returnFocus;
    document.querySelector('[data-watch-ad-open]')?.addEventListener('click', (event) => {
        returnFocus = event.currentTarget;
        dialog.showModal();
        document.documentElement.classList.add('watch-ad-open');
    });
    dialog.querySelector('[data-watch-ad-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        document.documentElement.classList.remove('watch-ad-open');
        returnFocus?.focus();
    });
}
