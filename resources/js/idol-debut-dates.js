document.querySelectorAll('[data-debut-date-editor]').forEach((editor) => {
    const list = editor.querySelector('[data-debut-date-list]');
    const template = editor.querySelector('[data-debut-date-template]');
    const addButton = editor.querySelector('[data-add-debut-date]');
    let nextIndex = Number(editor.dataset.nextIndex || 0);

    editor.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-debut-date]');
        if (removeButton) {
            removeButton.closest('[data-debut-date-row]').remove();
            return;
        }
        if (!event.target.closest('[data-add-debut-date]')) return;

        const row = template.content.firstElementChild.cloneNode(true);
        [row, ...row.querySelectorAll('*')].forEach((element) => {
            [...element.attributes].forEach((attribute) => {
                if (attribute.value.includes('__INDEX__')) {
                    element.setAttribute(attribute.name, attribute.value.replaceAll('__INDEX__', String(nextIndex)));
                }
            });
        });
        nextIndex += 1;
        list.append(row);
        window.initializeCountryPicker?.(row.querySelector('[data-country-picker]'));
    });
});

const isoDateError = 'Enter a valid date in YYYY-MM-DD format.';
const futureDateError = 'Date cannot be later than today.';

const syncIsoDateControl = (control) => {
    const textInput = control.querySelector('[data-iso-date-text]');
    const datePicker = control.querySelector('[data-iso-date-picker]');
    const value = textInput.value.trim();

    if (!value) {
        textInput.setCustomValidity('');
        datePicker.value = '';
        return;
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) {
        textInput.setCustomValidity(isoDateError);
        datePicker.value = '';
        return;
    }

    const [, year, month, day] = match.map(Number);
    const date = new Date(0);
    date.setUTCHours(0, 0, 0, 0);
    date.setUTCFullYear(year, month - 1, day);

    if (year < 1 || date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
        textInput.setCustomValidity(isoDateError);
        datePicker.value = '';
        return;
    }

    if (textInput.dataset.maxDate && value > textInput.dataset.maxDate) {
        textInput.setCustomValidity(futureDateError);
        datePicker.value = '';
        return;
    }

    textInput.setCustomValidity('');
    datePicker.value = value;
};

document.querySelectorAll('[data-iso-date-control]').forEach(syncIsoDateControl);

document.addEventListener('input', (event) => {
    const textInput = event.target.closest('[data-iso-date-text]');
    if (textInput) syncIsoDateControl(textInput.closest('[data-iso-date-control]'));
});

document.addEventListener('change', (event) => {
    const picker = event.target.closest('[data-iso-date-picker]');
    if (picker) {
        const control = picker.closest('[data-iso-date-control]');
        control.querySelector('[data-iso-date-text]').value = picker.value;
        syncIsoDateControl(control);
    }
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-open-iso-date-picker]');
    if (!button) return;

    const picker = button.closest('[data-iso-date-control]').querySelector('[data-iso-date-picker]');
    try {
        if (typeof picker.showPicker === 'function') picker.showPicker();
        else {
            picker.focus({ preventScroll: true });
            picker.click();
        }
    } catch {
        picker.focus({ preventScroll: true });
        picker.click();
    }
});
