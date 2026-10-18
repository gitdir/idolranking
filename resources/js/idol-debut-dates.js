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
