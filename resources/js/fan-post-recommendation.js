const button = document.querySelector('[data-fan-post-recommend]');

if (button) {
    const count = document.querySelector('[data-recommendation-count]');
    const monthlyCount = document.querySelector('[data-monthly-recommendation-count]');
    const label = button.querySelector('span');
    const formatter = new Intl.NumberFormat('en-US');

    button.addEventListener('click', async () => {
        if (button.dataset.signedIn !== 'true') {
            document.dispatchEvent(new CustomEvent('idolranking:sign-in'));
            return;
        }

        button.disabled = true;
        let recommendationSaved = false;
        try {
            const response = await fetch(button.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': button.dataset.csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ request_key: button.dataset.requestKey }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) return;

            recommendationSaved = Boolean(result.data.recommended);
            button.setAttribute('aria-pressed', String(recommendationSaved));
            label.textContent = window.idolRankingT('recommend');
            count.textContent = formatter.format(result.data.recommendations);
            monthlyCount.textContent = formatter.format(result.data.monthly_recommendations);
        } catch (error) {
            console.error('Fan Post recommendation request failed.', error);
        } finally {
            if (!recommendationSaved) button.disabled = false;
        }
    });
}
