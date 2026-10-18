document.querySelectorAll('[data-voting-trend]').forEach((chart) => {
    const tooltip = chart.querySelector('[data-voting-trend-tooltip]');
    const numberFormat = new Intl.NumberFormat(document.documentElement.lang || undefined);
    let touchTimeout;

    if (!tooltip) return;

    const hide = () => {
        tooltip.hidden = true;
    };

    const show = (point) => {
        tooltip.textContent = `${point.dataset.date} · ${numberFormat.format(Number(point.dataset.votes))} ${point.dataset.heartLabel || chart.dataset.heartLabel || ''}`.trim();
        tooltip.hidden = false;

        const chartRect = chart.getBoundingClientRect();
        const pointRect = point.getBoundingClientRect();
        const tooltipWidth = tooltip.offsetWidth;
        const tooltipHeight = tooltip.offsetHeight;
        const pointCenter = pointRect.left + pointRect.width / 2 - chartRect.left;
        const left = Math.min(
            Math.max(pointCenter - tooltipWidth / 2, 8),
            Math.max(8, chart.clientWidth - tooltipWidth - 8),
        );
        const pointTop = pointRect.top - chartRect.top;
        const top = pointTop >= tooltipHeight + 10 ? pointTop - tooltipHeight - 10 : pointTop + pointRect.height + 10;

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    };

    chart.querySelectorAll('.trend-point').forEach((point) => {
        point.addEventListener('pointerenter', (event) => {
            if (event.pointerType !== 'touch') show(point);
        });
        point.addEventListener('pointermove', (event) => {
            if (event.pointerType !== 'touch') show(point);
        });
        point.addEventListener('pointerleave', () => {
            if (document.activeElement !== point) hide();
        });
        point.addEventListener('focus', () => show(point));
        point.addEventListener('blur', hide);
        point.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'touch') return;
            show(point);
            window.clearTimeout(touchTimeout);
            touchTimeout = window.setTimeout(hide, 2500);
        });
    });
});
