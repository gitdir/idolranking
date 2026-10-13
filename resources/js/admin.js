import { initSquareImageCrop } from './square-image-crop';
import './image-fallback';

document.querySelectorAll('[data-admin-image-crop]').forEach((form) => {
    const imageInput = form.querySelector('[data-image-input]');
    const selection = form.querySelector('[data-image-selection]');
    const preview = form.querySelector('[data-image-preview]');
    const dialog = document.querySelector(form.dataset.adminImageCrop);
    if (!imageInput || !selection || !preview || !dialog) return;

    const crop = initSquareImageCrop({
        imageInput,
        selection,
        preview,
        dialog,
        openButton: form.querySelector('[data-crop-open]'),
        allowedTypes: ['image/jpeg', 'image/png'],
        invalidMessage: 'JPG 또는 PNG 이미지(최대 5MB)를 선택해 주세요.',
        outputName: 'idol-profile.png',
        existingImage: form.dataset.existingImage || '',
    });
    form.querySelector('[data-image-remove]')?.addEventListener('click', crop.clear);
});

const visitorChartCanvas = document.querySelector('[data-admin-visitor-chart]');
const visitorChartData = document.querySelector('[data-admin-visitor-chart-data]');

if (visitorChartCanvas && visitorChartData) {
    Promise.all([
        import('chart.js'),
        Promise.resolve(JSON.parse(visitorChartData.textContent || '[]')),
    ]).then(([chartModule, points]) => {
        const { Chart, registerables } = chartModule;
        Chart.register(...registerables);

        new Chart(visitorChartCanvas, {
            type: 'line',
            data: {
                labels: points.map((point) => point.date),
                datasets: [{
                    label: 'PV',
                    data: points.map((point) => point.pv),
                    borderColor: '#ef5da8',
                    backgroundColor: 'rgba(239, 93, 168, 0.12)',
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#ef5da8',
                    pointRadius: points.length > 62 ? 0 : 3,
                    pointHoverRadius: 5,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.25,
                }, {
                    label: 'UV',
                    data: points.map((point) => point.uv),
                    borderColor: '#6841df',
                    backgroundColor: 'rgba(104, 65, 223, 0.08)',
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#6841df',
                    pointRadius: points.length > 62 ? 0 : 3,
                    pointHoverRadius: 5,
                    borderWidth: 2,
                    fill: false,
                    tension: 0.25,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: {
                        callbacks: {
                            title: (items) => points[items[0].dataIndex].date,
                            label: (context) => `${context.dataset.label} ${Number(context.parsed.y).toLocaleString('ko-KR')}`,
                        },
                    },
                },
                scales: {
                    x: {
                        title: { display: true, text: '날짜' },
                        grid: { display: false },
                        ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 },
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: '건수' },
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    });
}

document.querySelectorAll('[data-open-admin-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById(button.dataset.openAdminDialog)?.showModal();
    });
});

document.querySelectorAll('[data-close-admin-dialog]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('.admin-dialog').forEach((dialog) => {
    if (dialog.hasAttribute('data-open-on-load')) dialog.showModal();
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});

const sidebarGroups = Array.from(document.querySelectorAll('[data-sidebar-group]'));

sidebarGroups.forEach((group) => {
    const toggle = group.querySelector('[data-sidebar-group-toggle]');
    const submenu = group.querySelector('.admin-sidebar-submenu');
    if (!toggle || !submenu) return;

    toggle.addEventListener('click', () => {
        const shouldOpen = submenu.hidden;

        sidebarGroups.forEach((otherGroup) => {
            const otherToggle = otherGroup.querySelector('[data-sidebar-group-toggle]');
            const otherSubmenu = otherGroup.querySelector('.admin-sidebar-submenu');

            otherGroup.dataset.open = 'false';
            otherToggle?.setAttribute('aria-expanded', 'false');
            if (otherSubmenu) otherSubmenu.hidden = true;
        });

        if (shouldOpen) {
            group.dataset.open = 'true';
            toggle.setAttribute('aria-expanded', 'true');
            submenu.hidden = false;
        }
    });
});
