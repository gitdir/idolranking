const headerSearch = document.querySelector('#idol-search');

if (headerSearch) {
    const parameters = new URLSearchParams(window.location.search);

    if (window.location.pathname === '/search') {
        headerSearch.value = parameters.get('q')?.trim() ?? '';
    }

    headerSearch.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;

        event.preventDefault();
        const query = headerSearch.value.trim();
        window.location.assign(query === '' ? '/search' : `/search?q=${encodeURIComponent(query)}`);
    });
}
