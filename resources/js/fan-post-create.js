import { initSquareImageCrop } from './square-image-crop';

const form = document.querySelector('[data-fan-post-create]');

if (form) {
    const mediaType = form.querySelector('[data-media-type]');
    const tabs = [...form.querySelectorAll('[data-media-tab]')];
    const panels = [...form.querySelectorAll('[data-media-panel]')];
    const imageInput = form.querySelector('[data-image-input]');
    const youtubeInput = form.querySelector('#fan-post-youtube');
    const selection = form.querySelector('[data-image-selection]');
    const preview = form.querySelector('[data-image-preview]');
    const existingImage = form.dataset.existingImage;
    let crop;

    const clearImage = () => {
        crop.clear();
        if (mediaType.value !== 'youtube') mediaType.value = 'text';
    };
    const setMedia = (type, force = false) => {
        const currentTab = mediaType.value === 'youtube' ? 'youtube' : 'image';
        if (!force && type !== currentTab && ((type === 'youtube' && (imageInput.files.length || existingImage)) || (type === 'image' && youtubeInput.value.trim()))) {
            if (!window.confirm('Switching media type will remove the current media selection.')) return;
        }
        mediaType.value = type === 'youtube' ? 'youtube' : (imageInput.files.length || (!selection.hidden && preview.src) ? 'image' : 'text');
        tabs.forEach((tab) => {
            const active = tab.dataset.mediaTab === type;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        });
        panels.forEach((panel) => { panel.hidden = panel.dataset.mediaPanel !== type; });
        if (type === 'image') youtubeInput.value = '';
        else clearImage();
    };
    tabs.forEach((tab) => tab.addEventListener('click', () => setMedia(tab.dataset.mediaTab)));

    const updateCounter = (field) => {
        form.querySelector(`output[for="${field.id}"]`).textContent = `${field.value.length}/${field.maxLength}`;
    };
    ['fan-post-title', 'fan-post-content'].forEach((id) => {
        const field = document.getElementById(id);
        field.addEventListener('input', () => updateCounter(field));
    });

    crop = initSquareImageCrop({
        imageInput,
        selection,
        preview,
        dialog: document.querySelector('[data-crop-dialog]'),
        openButton: form.querySelector('[data-crop-open]'),
        existingImage,
        outputName: 'fan-post-image.png',
        onSelect: () => { mediaType.value = 'image'; },
        onClear: () => { if (mediaType.value !== 'youtube') mediaType.value = 'text'; },
    });
    form.querySelector('[data-image-remove]').addEventListener('click', clearImage);

    const tagsValue = form.querySelector('[data-tags-value]');
    const tagEntry = document.getElementById('fan-post-tag-entry');
    const chips = form.querySelector('[data-tag-chips]');
    let tags = tagsValue.value.split(',').map((tag) => tag.trim()).filter(Boolean);
    const renderTags = () => {
        chips.replaceChildren(...tags.map((tag) => {
            const chip = document.createElement('span'); chip.className = 'fan-post-tag'; chip.append(`#${tag}`);
            const remove = document.createElement('button'); remove.type = 'button'; remove.setAttribute('aria-label', `Remove ${tag}`); remove.textContent = '×';
            remove.addEventListener('click', () => { tags = tags.filter((value) => value !== tag); renderTags(); });
            chip.append(remove); return chip;
        }));
        tagsValue.value = tags.join(',');
    };
    tagEntry.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ',') return;
        event.preventDefault();
        const tag = tagEntry.value.trim().replace(/^#/, '');
        if (tag && /^[\p{L}\p{N}_-]+$/u.test(tag) && tag.length <= 30 && tags.length < 20 && !tags.some((value) => value.toLowerCase() === tag.toLowerCase())) tags.push(tag);
        tagEntry.value = ''; renderTags();
    });
    renderTags();
    setMedia(mediaType.value === 'youtube' ? 'youtube' : 'image', true);
}
