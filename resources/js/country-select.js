const initializeCountryPicker = (picker) => {
    if (!picker || picker.dataset.countryPickerInitialized === 'true') return;
    picker.dataset.countryPickerInitialized = 'true';
    const trigger = picker.querySelector('[data-country-trigger]');
    const label = picker.querySelector('[data-country-label]');
    const code = picker.querySelector('[data-country-code]');
    const panel = picker.querySelector('[data-country-panel]');
    const search = picker.querySelector('[data-country-search]');
    const empty = picker.querySelector('[data-country-empty]');
    const options = [...picker.querySelectorAll('[data-country-option]')];
    let visibleOptions = options;
    let activeIndex = -1;

    const setActive = (index) => {
        options.forEach((option) => option.classList.remove('is-active'));
        if (!visibleOptions.length) {
            activeIndex = -1;
            return;
        }
        activeIndex = (index + visibleOptions.length) % visibleOptions.length;
        visibleOptions[activeIndex].classList.add('is-active');
        visibleOptions[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    const filter = () => {
        const query = search.value.trim().toLocaleLowerCase();
        visibleOptions = options.filter((option) => {
            const searchable = `${option.dataset.native} ${option.dataset.english} ${option.dataset.code}`.toLocaleLowerCase();
            const matches = searchable.includes(query);
            option.hidden = !matches;
            return matches;
        });
        empty.hidden = visibleOptions.length !== 0;
        const selectedIndex = visibleOptions.findIndex((option) => option.dataset.code === code.value);
        setActive(selectedIndex >= 0 ? selectedIndex : 0);
    };

    const close = (restoreFocus = false) => {
        if (panel.hidden) return;
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        picker.classList.remove('is-open');
        search.value = '';
        options.forEach((option) => { option.hidden = false; option.classList.remove('is-active'); });
        visibleOptions = options;
        activeIndex = -1;
        if (restoreFocus) trigger.focus();
    };

    const open = () => {
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        picker.classList.add('is-open');
        filter();
        search.focus();
    };

    const select = (option) => {
        code.value = option.dataset.code;
        label.textContent = option.dataset.name;
        label.classList.remove('country-picker-placeholder');
        options.forEach((item) => item.setAttribute('aria-selected', String(item === option)));
        search.setCustomValidity('');
        close(true);
        code.dispatchEvent(new Event('change', { bubbles: true }));
    };

    trigger.addEventListener('click', () => panel.hidden ? open() : close(true));
    trigger.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowDown') return;
        event.preventDefault();
        open();
    });
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            select(visibleOptions[activeIndex]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close(true);
        }
    });
    options.forEach((option) => option.addEventListener('click', () => select(option)));
    picker.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            close(true);
        }
    });
    document.addEventListener('pointerdown', (event) => {
        if (!panel.hidden && !picker.contains(event.target)) close();
    });
    if (picker.dataset.countryRequired === 'true') {
        trigger.form?.addEventListener('submit', (event) => {
            if (code.value) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            open();
            search.setCustomValidity('Select a country from the list.');
            search.reportValidity();
        }, { capture: true });
    }
};

window.initializeCountryPicker = initializeCountryPicker;
document.querySelectorAll('[data-country-picker]').forEach(initializeCountryPicker);
