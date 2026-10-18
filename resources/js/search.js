const searchDialog = document.querySelector('#site-search-dialog');
const searchInput = searchDialog?.querySelector('#idol-search');
const searchForm = searchDialog?.querySelector('[data-site-search-form]');
const searchTriggers = document.querySelectorAll('[data-search-open]');

if (searchDialog && searchInput && searchForm) {
    let returnFocus = null;

    searchTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            if (searchDialog.open) return;

            returnFocus = trigger;
            searchDialog.showModal();
            document.documentElement.classList.add('search-dialog-open');
            window.requestAnimationFrame(() => searchInput.focus());
        });
    });

    searchDialog.querySelector('[data-search-close]')?.addEventListener('click', () => searchDialog.close());
    searchDialog.addEventListener('click', (event) => {
        if (event.target !== searchDialog) return;

        const bounds = searchDialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
            searchDialog.close();
        }
    });
    searchDialog.addEventListener('close', () => {
        document.documentElement.classList.remove('search-dialog-open');
        returnFocus?.focus();
    });
    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const query = searchInput.value.trim();
        const destination = new URL(searchForm.action, window.location.origin);

        if (query === '') {
            destination.searchParams.delete('q');
        } else {
            destination.searchParams.set('q', query);
        }

        window.location.assign(destination.toString());
    });
}
