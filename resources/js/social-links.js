document.querySelectorAll('[data-social-link-list]').forEach((list) => {
    const items = list.querySelector('[data-social-link-items]');
    const addButton = list.querySelector('[data-social-link-add]');
    const maxLinks = Number(list.dataset.maxLinks);
    const duplicateKey = (value) => {
        try {
            const url = new URL(value.trim());
            return `${url.protocol}//${url.host}${url.pathname}${url.search}`;
        } catch {
            return value.trim().toLowerCase();
        }
    };
    const alertIfDuplicate = () => {
        const seen = new Set();
        for (const input of items.querySelectorAll('input[name="sns_links[]"]')) {
            if (!input.value.trim() || input.disabled) continue;
            const key = duplicateKey(input.value);
            if (seen.has(key)) {
                window.alert(list.dataset.duplicateMessage);
                input.focus();
                return true;
            }
            seen.add(key);
        }
        return false;
    };
    const updateControls = () => {
        const rows = [...items.querySelectorAll('[data-social-link-row]')];
        rows.forEach((row, index) => {
            const input = row.querySelector('input');
            if (input) {
                input.id = `${list.dataset.idPrefix}-${index}`;
                input.setAttribute('aria-label', list.dataset.inputLabel.replace(':number', index + 1));
            }
            let removeButton = row.querySelector('[data-social-link-remove]');
            if (index === 0) {
                removeButton?.remove();
                return;
            }
            if (!removeButton) {
                removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'social-link-remove';
                removeButton.dataset.socialLinkRemove = '';
                removeButton.textContent = '×';
                row.append(removeButton);
            }
            removeButton.setAttribute('aria-label', list.dataset.removeLabel);
        });
        addButton.hidden = rows.length >= maxLinks;
        addButton.disabled = list.dataset.disabled === 'true' || rows.length >= maxLinks;
    };

    addButton.addEventListener('click', () => {
        const rows = items.querySelectorAll('[data-social-link-row]');
        if (rows.length >= maxLinks) return;
        const index = rows.length;
        const row = document.createElement('div');
        row.className = 'social-link-input-row';
        row.dataset.socialLinkRow = '';
        const input = document.createElement('input');
        input.id = `${list.dataset.idPrefix}-${index}`;
        input.name = 'sns_links[]';
        input.type = 'url';
        input.maxLength = 2048;
        input.placeholder = list.dataset.placeholder;
        input.setAttribute('aria-label', list.dataset.inputLabel.replace(':number', index + 1));
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'social-link-remove';
        remove.dataset.socialLinkRemove = '';
        remove.setAttribute('aria-label', list.dataset.removeLabel);
        remove.textContent = '×';
        row.append(input, remove);
        items.append(row);
        updateControls();
        input.focus();
    });

    items.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-social-link-remove]');
        if (!removeButton) return;
        removeButton.closest('[data-social-link-row]')?.remove();
        updateControls();
    });

    items.addEventListener('change', (event) => {
        if (event.target.matches('input[name="sns_links[]"]')) alertIfDuplicate();
    });
    list.closest('form')?.addEventListener('submit', (event) => {
        if (alertIfDuplicate()) event.preventDefault();
    });

    updateControls();
});
