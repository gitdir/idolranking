const defaultImage = document.querySelector('meta[name="default-image"]')?.content;

if (defaultImage) {
    const defaultImageUrl = new URL(defaultImage, window.location.href).href;
    const updateFallbackClass = (image) => {
        const imageUrl = new URL(image.currentSrc || image.src, window.location.href).href;
        image.classList.toggle('global-default-image', imageUrl === defaultImageUrl);
    };

    document.querySelectorAll('img').forEach(updateFallbackClass);

    document.addEventListener('load', (event) => {
        if (event.target instanceof HTMLImageElement) {
            updateFallbackClass(event.target);
        }
    }, true);

    document.addEventListener('error', (event) => {
        const image = event.target;
        if (!(image instanceof HTMLImageElement) || image.dataset.defaultImageApplied === 'true') {
            return;
        }

        image.dataset.defaultImageApplied = 'true';
        image.classList.add('global-default-image');
        image.src = defaultImage;
    }, true);
}
