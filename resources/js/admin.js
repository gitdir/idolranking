import { initSquareImageCrop } from './square-image-crop';
import './image-fallback';
import './social-links';
import './country-select';
import './idol-debut-dates';

document.querySelectorAll('[data-admin-heart-grant-form]').forEach((form) => {
    const submitButton = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';
        if (submitButton) submitButton.disabled = true;
    });
});

document.querySelectorAll('[data-admin-group-autocomplete]').forEach((autocomplete) => {
    const input = autocomplete.querySelector('[data-group-search]');
    const groupId = autocomplete.querySelector('[data-group-id]');
    const results = autocomplete.querySelector('[data-group-results]');
    const status = autocomplete.querySelector('[data-group-status]');
    const noGroup = autocomplete.querySelector('[data-group-none]');
    const form = autocomplete.closest('form');
    let timer;
    let controller;
    let activeIndex = -1;

    const close = () => {
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    };
    const displayName = (group) => {
        const english = (group.localized_english_name || group.english_name || group.name || '').trim();
        const korean = (group.localized_korean_name || group.name || '').trim();
        return korean && korean.toLocaleLowerCase() !== english.toLocaleLowerCase() ? `${english} · ${korean}` : english || korean;
    };
    const render = (groups) => {
        const fragment = document.createDocumentFragment();
        groups.forEach((group) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'idol-group-result';
            option.id = `admin-idol-group-option-${group.idx}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            const english = document.createElement('strong');
            english.textContent = group.localized_english_name || group.english_name || group.name;
            english.setAttribute('translate', 'no');
            option.append(english);
            const koreanName = group.localized_korean_name || group.name;
            if (koreanName && koreanName.toLocaleLowerCase() !== english.textContent.toLocaleLowerCase()) {
                const korean = document.createElement('span');
                korean.textContent = koreanName;
                korean.setAttribute('translate', 'no');
                option.append(korean);
            }
            option.addEventListener('click', () => {
                groupId.value = String(group.idx);
                input.value = displayName(group);
                input.dataset.noGroupSelected = 'false';
                input.dataset.selectedGender = group.gender;
                status.textContent = `그룹을 선택했습니다: ${input.value}`;
                results.replaceChildren();
                close();
            });
            fragment.append(option);
        });
        results.replaceChildren(fragment);
        results.hidden = groups.length === 0;
        input.setAttribute('aria-expanded', String(groups.length > 0));
        status.textContent = groups.length ? '' : '검색 결과가 없습니다.';
    };
    const search = async (term) => {
        controller?.abort();
        const requestController = new AbortController();
        controller = requestController;
        status.textContent = '그룹을 검색하고 있습니다.';
        input.setAttribute('aria-busy', 'true');
        results.hidden = true;
        try {
            const params = new URLSearchParams({ request_type: autocomplete.dataset.requestType, q: term });
            const response = await fetch(`${autocomplete.dataset.searchUrl}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin', signal: requestController.signal,
            });
            const data = await response.json();
            if (!response.ok || !data.success || !Array.isArray(data.groups)) throw new Error('search_failed');
            render(data.groups);
        } catch (error) {
            if (error.name !== 'AbortError') {
                results.replaceChildren();
                close();
                status.textContent = '그룹 검색 중 오류가 발생했습니다. 다시 시도해 주세요.';
            }
        } finally {
            if (controller === requestController) input.removeAttribute('aria-busy');
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        groupId.value = '';
        input.dataset.selectedGender = '';
        input.dataset.noGroupSelected = 'false';
        results.replaceChildren();
        status.textContent = '';
        close();
        const term = input.value.trim();
        if (term) timer = setTimeout(() => search(term), 275);
    });
    input.addEventListener('keydown', (event) => {
        const options = [...results.querySelectorAll('[role="option"]')];
        if (event.key === 'Escape') { close(); return; }
        if (!options.length || results.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = activeIndex < 0 ? (event.key === 'ArrowDown' ? 0 : options.length - 1)
                : (activeIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === activeIndex)));
            input.setAttribute('aria-activedescendant', options[activeIndex].id);
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            options[activeIndex].click();
        }
    });
    noGroup.addEventListener('click', () => {
        clearTimeout(timer);
        controller?.abort();
        groupId.value = '';
        input.dataset.selectedGender = '';
        input.dataset.noGroupSelected = 'true';
        input.value = noGroup.textContent.trim();
        results.replaceChildren();
        status.textContent = '소속 그룹이 없는 것으로 선택했습니다.';
        close();
    });
    form?.addEventListener('submit', (event) => {
        if (input.value.trim() && !groupId.value && input.dataset.noGroupSelected !== 'true') {
            event.preventDefault();
            status.textContent = '검색 결과에서 그룹을 선택하거나 소속 그룹 없음을 선택해 주세요.';
            input.focus();
        }
    });
    document.addEventListener('pointerdown', (event) => {
        if (!autocomplete.contains(event.target)) close();
    });
});

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

const localizationEditor = document.querySelector('[data-localization-editor]');
if (localizationEditor) {
    const localizationRows = localizationEditor.querySelector('[data-localization-rows]');
    const localizationTemplate = localizationEditor.querySelector('[data-localization-template]');
    const localizationEmpty = localizationEditor.querySelector('[data-localization-empty]');
    const englishNameInput = document.querySelector('input[name="english_name"]');
    const languageFeedback = localizationEditor.querySelector('[data-language-feedback]');
    const languageLabels = { en: '영어', ko: '한국어', ja: '일본어', 'zh-CN': '중국어 간체', 'zh-TW': '중국어 번체' };

    const languageCode = (row) => row.querySelector('[data-language-code]')?.value.trim() ?? '';
    const refreshLanguageRow = (row, clearOnLeave = false) => {
        const languageInput = row.querySelector('[data-language-code]');
        const choice = row.querySelector('[data-language-choice]');
        const customCode = row.querySelector('[data-custom-language-code]');
        const nameInput = row.querySelector('[data-localized-name]');
        const englishHelp = row.querySelector('[data-english-name-help]');
        const deleteButton = row.querySelector('[data-remove-localization]');
        if (!languageInput || !choice) return;

        if (choice.value === 'other') {
            customCode.hidden = false;
            customCode.disabled = row.dataset.existingLanguage === 'true';
            languageInput.value = customCode.value.trim();
        } else {
            customCode.hidden = true;
            customCode.disabled = true;
            languageInput.value = choice.value;
        }

        const code = languageCode(row);
        const isEnglish = code === 'en';
        if (nameInput) {
            if (isEnglish) {
                nameInput.value = englishNameInput?.value ?? localizationEditor.dataset.canonicalEnglishName ?? '';
                nameInput.readOnly = true;
            } else if (nameInput.readOnly) {
                if (clearOnLeave) nameInput.value = '';
                nameInput.readOnly = false;
            }
        }
        if (englishHelp) englishHelp.hidden = !isEnglish;
        if (deleteButton) deleteButton.hidden = isEnglish;
        const label = row.querySelector('[data-language-label]');
        if (label) label.textContent = code ? `${languageLabels[code] || '기타'} (${code})` : '새 언어';
    };

    const validateLanguageRows = () => {
        const rows = [...localizationRows.querySelectorAll('[data-localization-row]')];
        const counts = new Map();
        rows.forEach((row) => {
            const code = languageCode(row).toLocaleLowerCase();
            if (code) counts.set(code, (counts.get(code) || 0) + 1);
        });
        const duplicates = [...counts.values()].some((count) => count > 1);
        const missing = rows.some((row) => !languageCode(row));
        languageFeedback.textContent = duplicates
            ? '같은 언어 카드를 중복해서 추가할 수 없습니다.'
            : (missing ? '각 언어 카드의 언어를 선택하거나 언어 코드를 입력해 주세요.' : '');
        return !duplicates && !missing;
    };
    const syncLocalizationEmptyState = () => {
        if (localizationEmpty && localizationRows) {
            localizationEmpty.hidden = localizationRows.querySelector('[data-localization-row]') !== null;
        }
    };

    const addAliasInput = (row, value = '') => {
        const items = row.querySelector('[data-alias-items]');
        if (!items) return;

        const aliasRow = document.createElement('div');
        aliasRow.className = 'admin-multilingual-alias';
        aliasRow.dataset.aliasRow = '';

        const input = document.createElement('input');
        input.name = `localizations[${row.dataset.rowIndex}][aliases][]`;
        input.value = value;
        input.maxLength = 200;
        input.setAttribute('aria-label', '별칭 이름');

        const remove = document.createElement('button');
        remove.className = 'admin-alias-remove';
        remove.type = 'button';
        remove.dataset.removeAlias = '';
        remove.textContent = '×';
        remove.setAttribute('aria-label', '별칭 삭제');
        remove.title = '별칭 삭제';

        aliasRow.append(input, remove);
        items.append(aliasRow);
    };

    localizationRows.querySelectorAll('[data-localization-row]').forEach((row) => refreshLanguageRow(row));
    localizationEditor.addEventListener('input', (event) => {
        if (event.target.matches('[data-custom-language-code]')) {
            refreshLanguageRow(event.target.closest('[data-localization-row]'));
            validateLanguageRows();
        }
    });
    localizationEditor.addEventListener('change', (event) => {
        if (event.target.matches('[data-language-choice]')) {
            refreshLanguageRow(event.target.closest('[data-localization-row]'), true);
            validateLanguageRows();
            if (event.target.value === 'other') event.target.closest('[data-localization-row]')?.querySelector('[data-custom-language-code]')?.focus();
        }
    });
    englishNameInput?.addEventListener('input', () => {
        localizationEditor.dataset.canonicalEnglishName = englishNameInput.value;
        localizationRows?.querySelectorAll('[data-localization-row]').forEach((row) => refreshLanguageRow(row));
    });

    localizationEditor.closest('form')?.addEventListener('submit', (event) => {
        if (!validateLanguageRows()) {
            event.preventDefault();
            localizationRows.querySelector('[data-language-choice]:not(:disabled)')?.focus();
        }
    });

    localizationEditor.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;

        const row = button.closest('[data-localization-row]');
        if (button.matches('[data-add-localization]') && localizationTemplate && localizationRows) {
            const index = Number(localizationRows.dataset.nextIndex || 0);
            localizationRows.dataset.nextIndex = String(index + 1);
            const fragment = localizationTemplate.content.cloneNode(true);
            fragment.querySelectorAll('*').forEach((element) => {
                Array.from(element.attributes).forEach((attribute) => {
                    if (attribute.value.includes('__INDEX__')) {
                        element.setAttribute(attribute.name, attribute.value.replaceAll('__INDEX__', String(index)));
                    }
                });
            });
            const addedRow = fragment.querySelector('[data-localization-row]');
            localizationRows.append(fragment);
            syncLocalizationEmptyState();
            addedRow?.querySelector('[data-language-choice]')?.focus();
        } else if (button.matches('[data-remove-localization]')) {
            if (languageCode(row) === 'en') {
                languageFeedback.textContent = '영어 기준 이름은 기본 정보에서 관리하므로 삭제할 수 없습니다.';
            } else {
                row?.remove();
                syncLocalizationEmptyState();
                validateLanguageRows();
            }
        } else if (button.matches('[data-add-alias]') && row) {
            addAliasInput(row);
        } else if (button.matches('[data-remove-alias]')) {
            button.closest('[data-alias-row]')?.remove();
        }
    });
}

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

const adminAccount = document.querySelector('[data-admin-account]');

if (adminAccount) {
    const accountTrigger = adminAccount.querySelector('.admin-account-trigger');
    const accountMenu = adminAccount.querySelector('.admin-account-menu');

    if (accountTrigger && accountMenu) {
        const closeAccountMenu = (returnFocus = false) => {
            accountMenu.hidden = true;
            accountTrigger.setAttribute('aria-expanded', 'false');
            if (returnFocus) accountTrigger.focus();
        };

        accountTrigger.addEventListener('click', () => {
            const shouldOpen = accountMenu.hidden;
            accountMenu.hidden = !shouldOpen;
            accountTrigger.setAttribute('aria-expanded', String(shouldOpen));
            if (shouldOpen) accountMenu.querySelector('[data-admin-account-item]')?.focus();
        });

        accountMenu.addEventListener('click', (event) => {
            if (event.target.closest('[data-admin-account-item]')) closeAccountMenu();
        });

        adminAccount.addEventListener('focusout', (event) => {
            if (!adminAccount.contains(event.relatedTarget)) closeAccountMenu();
        });

        document.addEventListener('pointerdown', (event) => {
            if (!adminAccount.contains(event.target)) closeAccountMenu();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !accountMenu.hidden) {
                event.preventDefault();
                closeAccountMenu(true);
            }
        });
    }
}

const adminNavToggle = document.querySelector('[data-admin-nav-open]');
const adminSidebar = document.querySelector('#admin-sidebar');
const adminNavOverlay = document.querySelector('.admin-nav-overlay');
const adminNavCloseButtons = document.querySelectorAll('[data-admin-nav-close]');
const adminMobileNav = window.matchMedia('(max-width: 767px)');

if (adminNavToggle && adminSidebar && adminNavOverlay) {
    const closeAdminNav = (returnFocus = true) => {
        adminSidebar.classList.remove('is-open');
        adminSidebar.setAttribute('aria-hidden', 'true');
        adminSidebar.inert = true;
        adminNavToggle.setAttribute('aria-expanded', 'false');
        adminNavOverlay.hidden = true;
        document.documentElement.classList.remove('admin-nav-open');
        if (returnFocus && adminMobileNav.matches) adminNavToggle.focus();
    };

    const openAdminNav = () => {
        if (!adminMobileNav.matches) return;
        adminSidebar.classList.add('is-open');
        adminSidebar.setAttribute('aria-hidden', 'false');
        adminSidebar.inert = false;
        adminNavToggle.setAttribute('aria-expanded', 'true');
        adminNavOverlay.hidden = false;
        document.documentElement.classList.add('admin-nav-open');
        adminSidebar.querySelector('nav a, nav button')?.focus();
    };

    adminNavToggle.addEventListener('click', openAdminNav);
    adminNavCloseButtons.forEach((button) => button.addEventListener('click', () => closeAdminNav()));
    adminSidebar.querySelector('nav')?.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeAdminNav(false);
    });
    document.addEventListener('keydown', (event) => {
        if (!adminMobileNav.matches || !adminSidebar.classList.contains('is-open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeAdminNav();
            return;
        }
        if (event.key === 'Tab') {
            const focusable = [...adminSidebar.querySelectorAll('a[href], button:not([disabled])')];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (!first || !last) return;
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    adminMobileNav.addEventListener('change', (event) => {
        closeAdminNav(false);
        if (!event.matches) {
            adminSidebar.removeAttribute('aria-hidden');
            adminSidebar.inert = false;
        }
    });
    closeAdminNav(false);
    if (!adminMobileNav.matches) {
        adminSidebar.removeAttribute('aria-hidden');
        adminSidebar.inert = false;
    }
}

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
