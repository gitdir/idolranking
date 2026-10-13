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

const partnerRewardDialog = document.querySelector('#partner-banner-reward-dialog');
const partnerClaimButton = partnerRewardDialog?.querySelector('[data-partner-banner-claim]');
const partnerRewardAmount = partnerRewardDialog?.querySelector('[data-partner-banner-amount]');
let activePartnerBanner = null;

if (partnerRewardDialog && partnerClaimButton) {
    document.querySelectorAll('[data-partner-banner-reward]').forEach((banner) => {
        banner.addEventListener('click', () => {
            activePartnerBanner = banner;
            partnerRewardAmount.textContent = Number(banner.dataset.heartAmount).toLocaleString();
            partnerClaimButton.disabled = false;
            partnerClaimButton.textContent = '하트 받기';
            document.documentElement.classList.add('watch-ad-open');
            if (!partnerRewardDialog.open) partnerRewardDialog.showModal();
        });
    });

    partnerRewardDialog.querySelector('[data-partner-banner-close]')?.addEventListener('click', () => partnerRewardDialog.close());
    partnerRewardDialog.addEventListener('close', () => {
        document.documentElement.classList.remove('watch-ad-open');
        activePartnerBanner?.focus();
    });

    partnerClaimButton.addEventListener('click', async () => {
        if (!activePartnerBanner || partnerClaimButton.disabled) return;
        partnerClaimButton.disabled = true;
        partnerClaimButton.textContent = '처리 중…';

        try {
            const response = await fetch(activePartnerBanner.dataset.claimUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': partnerClaimButton.dataset.csrf,
                },
            });
            const result = await response.json();

            if (result.data?.heart_balance !== undefined) {
                document.querySelectorAll('[data-get-hearts-balance], [data-member-heart-balance]').forEach((element) => {
                    element.textContent = Number(result.data.heart_balance).toLocaleString();
                });
            }
            if (result.data?.today_earned !== undefined) {
                const todayEarned = document.querySelector('[data-get-hearts-today-earned]');
                if (todayEarned) todayEarned.textContent = `+ ${Number(result.data.today_earned).toLocaleString()}`;
            }

            if (result.data?.completed_today) {
                const badge = activePartnerBanner.querySelector('[data-partner-banner-badge]');
                if (badge) {
                    badge.textContent = '✓ 완료';
                    badge.classList.add('is-complete');
                }
                activePartnerBanner.removeAttribute('data-partner-banner-reward');
                activePartnerBanner.removeAttribute('data-claim-url');
            }

            document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: {
                type: response.ok || result.already_claimed ? 'success' : 'error',
                title: result.already_claimed ? 'Partner Banner' : (response.ok ? 'Reward received' : 'Unable to claim reward'),
                message: result.message || 'Please try again.',
            }}));

            if (response.ok || result.already_claimed) {
                partnerRewardDialog.close();
            } else {
                partnerClaimButton.disabled = false;
                partnerClaimButton.textContent = '하트 받기';
            }
        } catch {
            partnerClaimButton.disabled = false;
            partnerClaimButton.textContent = '하트 받기';
            document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: {
                type: 'error', title: 'Unable to claim reward', message: 'Please try again.',
            }}));
        }
    });
}
