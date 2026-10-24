import { initSquareImageCrop } from './square-image-crop';

const form = document.querySelector('[data-fan-post-create]');

if (form) {
    const categoryOptions = form.querySelector('[data-idol-category-options]');
    if (categoryOptions) {
        const categoryInputs = [...categoryOptions.querySelectorAll('[name="idol_category"]')];
        const idolSelect = form.querySelector('[data-idol-select]');
        const dependentFields = form.querySelector('[data-dependent-fields]');
        const registrationToggle = form.querySelector('[data-registration-toggle]');
        const registrationPanel = form.querySelector('[data-registration-panel]');
        const setDependentFields = (visible) => {
            dependentFields.hidden = !visible;
            dependentFields.querySelectorAll('input, select, textarea, button').forEach((control) => {
                control.disabled = !visible;
            });
        };
        const setIdolOptions = (idols, selectedIdol = '') => {
            const placeholder = idolSelect.options[0];
            idolSelect.replaceChildren(placeholder);
            idols.forEach((idol) => {
                const option = document.createElement('option');
                option.value = String(idol.idx);
                option.textContent = `${idol.name}${idol.type === 'group' ? ` (${categoryOptions.dataset.groupLabel})` : ''}`;
                option.translate = 'no';
                option.selected = option.value === String(selectedIdol);
                idolSelect.append(option);
            });
        };
        let categoryRequest;
        const loadCategory = async (category) => {
            categoryRequest?.abort();
            categoryRequest = new AbortController();
            idolSelect.disabled = true;
            const placeholder = idolSelect.options[0];
            idolSelect.replaceChildren(placeholder);
            try {
                const url = new URL(categoryOptions.dataset.idolCategoryUrl, window.location.origin);
                url.searchParams.set('category', category);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: categoryRequest.signal,
                });
                if (!response.ok) throw new Error('Unable to load idols for this category.');
                const data = await response.json();
                const idols = data.idols ?? [];
                setIdolOptions(idols);
                idolSelect.disabled = idols.length === 0;
            } catch (error) {
                if (error.name !== 'AbortError') console.error(error);
            }
        };

        categoryInputs.forEach((input) => input.addEventListener('change', () => {
            if (!input.checked) return;
            setDependentFields(false);
            idolSelect.value = '';
            loadCategory(input.value);
        }));
        idolSelect.addEventListener('change', () => {
            const hasSelection = Boolean(idolSelect.value);
            setDependentFields(hasSelection);
        });
        registrationToggle.addEventListener('click', () => {
            registrationPanel.hidden = !registrationPanel.hidden;
            registrationToggle.setAttribute('aria-expanded', String(!registrationPanel.hidden));
        });

        const selectedCategory = categoryInputs.find((input) => input.checked)?.value;
        const hasSelection = Boolean(selectedCategory && idolSelect.value);
        setDependentFields(Boolean(selectedCategory && hasSelection));
        if (selectedCategory && idolSelect.options.length <= 1) {
            loadCategory(selectedCategory);
        } else if (!selectedCategory) {
            idolSelect.disabled = true;
        }
    }

    if (!form.classList.contains('fan-post-edit-form')) {
        const validationMessage = form.dataset.validationAlertMessage;
        const fieldLabel = (control) => {
            if (control.name === 'idol_category') {
                return form.querySelector('.idol-request-type-row > .fan-post-create-label')?.textContent.replace(/\s*\*\s*$/, '').trim() || control.name;
            }
            const explicitLabel = control.labels?.[0]?.textContent.replace(/\s*\*\s*$/, '').trim();
            if (explicitLabel) return explicitLabel;
            const field = control.closest('.fan-post-create-field');
            const label = field?.querySelector(':scope > label, :scope > .fan-post-create-label');
            return label?.textContent.replace(/\s*\*\s*$/, '').trim() || control.name;
        };
        const focusInvalidField = (control) => {
            control.focus({ preventScroll: true });
            control.scrollIntoView({ behavior: 'smooth', block: 'center' });
        };

        form.addEventListener('submit', (event) => {
            const invalidFields = [...form.querySelectorAll('[required]:not(:disabled)')]
                .filter((control) => !control.closest('[hidden]') && !control.validity.valid);
            const mediaTypeInput = form.querySelector('[data-media-type]');
            const mediaType = mediaTypeInput?.value;
            const imageInput = form.querySelector('[data-image-input]');
            const youtubeInput = form.querySelector('#fan-post-youtube');
            if (mediaTypeInput && !['text', 'image', 'youtube'].includes(mediaType)) invalidFields.push(mediaTypeInput);
            if (mediaType === 'image' && imageInput && !imageInput.files.length) invalidFields.push(imageInput);
            if (mediaType === 'youtube' && youtubeInput && !youtubeInput.value.trim()) invalidFields.push(youtubeInput);

            const uniqueInvalidFields = [...new Set(invalidFields)];
            if (!uniqueInvalidFields.length) return;

            event.preventDefault();
            const labels = [...new Set(uniqueInvalidFields.map(fieldLabel))];
            window.alert(`${validationMessage}\n\n- ${labels.join('\n- ')}`);
            focusInvalidField(uniqueInvalidFields[0]);
        });
    }

    const mediaType = form.querySelector('[data-media-type]');
    const tabs = [...form.querySelectorAll('[data-media-tab]')];
    if (tabs.length) {
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
                if (!window.confirm(window.idolRankingT('switch_media_confirm'))) return;
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
        setMedia(mediaType.value === 'youtube' ? 'youtube' : 'image', true);
    }

    const updateCounter = (field) => {
        form.querySelector(`output[for="${field.id}"]`).textContent = `${field.value.length}/${field.maxLength}`;
    };
    ['fan-post-title', 'fan-post-content'].forEach((id) => {
        const field = document.getElementById(id);
        field.addEventListener('input', () => updateCounter(field));
    });

    const tagsValue = form.querySelector('[data-tags-value]');
    const tagEntry = document.getElementById('fan-post-tag-entry');
    const chips = form.querySelector('[data-tag-chips]');
    let tags = tagsValue?.value.split(',').map((tag) => tag.trim()).filter(Boolean) ?? [];
    const renderTags = () => {
        if (!tagsValue || !chips) return;
        chips.replaceChildren(...tags.map((tag) => {
            const chip = document.createElement('span'); chip.className = 'fan-post-tag'; chip.append(`#${tag}`);
            const remove = document.createElement('button'); remove.type = 'button'; remove.setAttribute('aria-label', window.idolRankingT('remove_tag', { tag })); remove.textContent = '×';
            remove.addEventListener('click', () => { tags = tags.filter((value) => value !== tag); renderTags(); });
            chip.append(remove); return chip;
        }));
        tagsValue.value = tags.join(',');
    };
    tagEntry?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ',') return;
        event.preventDefault();
        const tag = tagEntry.value.trim().replace(/^#/, '');
        if (tag && /^[\p{L}\p{N}_-]+$/u.test(tag) && tag.length <= 30 && tags.length < 20 && !tags.some((value) => value.toLowerCase() === tag.toLowerCase())) tags.push(tag);
        tagEntry.value = ''; renderTags();
    });
    if (tagsValue && chips) renderTags();
}
