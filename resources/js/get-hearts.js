const dialog = document.querySelector('#watch-ad-dialog');

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
            partnerClaimButton.textContent = window.idolRankingT('heart_reward');
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
        partnerClaimButton.textContent = window.idolRankingT('processing');

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
                document.querySelectorAll('[data-get-hearts-balance]').forEach((element) => {
                    element.textContent = Number(result.data.heart_balance).toLocaleString();
                });
                if (document.body.dataset.activityMemberIsAuthenticated === 'true') {
                    document.querySelectorAll('[data-member-heart-balance]').forEach((element) => {
                        element.textContent = Number(result.data.heart_balance).toLocaleString();
                    });
                }
            }
            if (result.data?.today_earned !== undefined) {
                const todayEarned = document.querySelector('[data-get-hearts-today-earned]');
                if (todayEarned) todayEarned.textContent = `+ ${Number(result.data.today_earned).toLocaleString()}`;
            }

            if (result.data?.completed_today) {
                const badge = activePartnerBanner.querySelector('[data-partner-banner-badge]');
                if (badge) {
                    badge.textContent = `✓ ${window.idolRankingT('complete')}`;
                    badge.classList.add('is-complete');
                }
                activePartnerBanner.removeAttribute('data-partner-banner-reward');
                activePartnerBanner.removeAttribute('data-claim-url');
            }

            document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: {
                type: response.ok || result.already_claimed ? 'success' : 'error',
                title: result.already_claimed ? window.idolRankingT('partner_banner') : (response.ok ? window.idolRankingT('reward_received') : window.idolRankingT('claim_failed')),
                message: result.message || window.idolRankingT('please_try_again'),
            }}));

            if (response.ok || result.already_claimed) {
                partnerRewardDialog.close();
            } else {
                partnerClaimButton.disabled = false;
                partnerClaimButton.textContent = window.idolRankingT('heart_reward');
            }
        } catch {
            partnerClaimButton.disabled = false;
            partnerClaimButton.textContent = window.idolRankingT('heart_reward');
            document.dispatchEvent(new CustomEvent('idolranking:notify', { detail: {
                type: 'error', title: window.idolRankingT('claim_failed'), message: window.idolRankingT('please_try_again'),
            }}));
        }
    });
}
