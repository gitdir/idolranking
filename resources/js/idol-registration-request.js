import { initSquareImageCrop } from './square-image-crop';

const requestForm = document.querySelector('[data-idol-request-form]');

if (requestForm) {
    const typeInputs = [...requestForm.querySelectorAll('[name="request_type"]')];
    const groupSearch = requestForm.querySelector('[data-group-search]');
    const groupId = requestForm.querySelector('[data-group-id]');
    const groupResults = requestForm.querySelector('[data-group-results]');
    const groupStatus = requestForm.querySelector('[data-group-status]');
    const noGroupButton = requestForm.querySelector('[data-group-none]');
    const groupAutocomplete = requestForm.querySelector('[data-group-autocomplete]');
    const individualFields = [...requestForm.querySelectorAll('[data-individual-only]')];
    const groupOnlyFields = [...requestForm.querySelectorAll('[data-group-only]')];
    const updateTypeFields = () => {
        clearTimeout(groupSearchTimer);
        if (groupSearchController) groupSearchController.abort();
        groupResults.replaceChildren();
        closeGroupResults();
        const type = typeInputs.find((input) => input.checked)?.value || 'boy';
        const isIndividual = type === 'boy' || type === 'girl';
        const gender = type.startsWith('boy') ? 'male' : 'female';
        requestForm.classList.toggle('is-group-type', !isIndividual);
        individualFields.forEach((field) => {
            field.hidden = !isIndividual;
            field.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = !isIndividual;
                if (!isIndividual) control.value = '';
            });
        });
        groupOnlyFields.forEach((field) => {
            field.hidden = isIndividual;
            field.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = isIndividual;
                if (isIndividual) control.value = '';
            });
        });
        groupSearch.dataset.requestType = type;
        if (groupId.value && groupSearch.dataset.selectedGender && groupSearch.dataset.selectedGender !== gender) {
            clearGroupSelection();
        }
    };
    typeInputs.forEach((input) => input.addEventListener('change', updateTypeFields));

    let groupSearchTimer = null;
    let groupSearchController = null;
    let groupActiveIndex = -1;

    const closeGroupResults = () => {
        groupResults.hidden = true;
        groupSearch.setAttribute('aria-expanded', 'false');
        groupSearch.removeAttribute('aria-activedescendant');
        groupActiveIndex = -1;
    };

    const clearGroupSelection = (clearText = true) => {
        groupId.value = '';
        groupSearch.dataset.selectedGender = '';
        if (clearText) groupSearch.value = '';
        closeGroupResults();
    };

    updateTypeFields();

    const displayGroupName = (group) => {
        const english = (group.english_name || group.name || '').trim();
        const korean = (group.name || '').trim();
        return korean && korean.toLocaleLowerCase() !== english.toLocaleLowerCase()
            ? `${english} · ${korean}`
            : english || korean;
    };

    const chooseGroup = (group) => {
        groupId.value = String(group.idx);
        groupSearch.dataset.selectedGender = group.gender;
        groupSearch.value = displayGroupName(group);
        groupResults.replaceChildren();
        groupStatus.textContent = window.idolRankingT('group_selected', { name: groupSearch.value });
        closeGroupResults();
    };

    const renderGroupResults = (groups) => {
        const fragment = document.createDocumentFragment();
        groups.forEach((group) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'idol-group-result';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.id = `idol-group-option-${group.idx}`;
            option.dataset.groupId = group.idx;
            option.dataset.groupGender = group.gender;
            const english = document.createElement('strong');
            english.textContent = group.english_name || group.name;
            english.setAttribute('translate', 'no');
            option.append(english);
            if (group.name && group.name.toLocaleLowerCase() !== (group.english_name || group.name).toLocaleLowerCase()) {
                const korean = document.createElement('span');
                korean.textContent = group.name;
                korean.setAttribute('translate', 'no');
                option.append(korean);
            }
            option.addEventListener('click', () => chooseGroup(group));
            fragment.append(option);
        });
        groupResults.replaceChildren(fragment);
        groupResults.hidden = groups.length === 0;
        groupSearch.setAttribute('aria-expanded', groups.length ? 'true' : 'false');
        groupStatus.textContent = groups.length ? '' : window.idolRankingT('group_search_empty');
    };

    const searchGroups = async (term) => {
        if (groupSearchController) groupSearchController.abort();
        const controller = new AbortController();
        groupSearchController = controller;
        groupStatus.textContent = window.idolRankingT('group_search_loading');
        groupResults.hidden = true;
        groupSearch.setAttribute('aria-busy', 'true');
        try {
            const params = new URLSearchParams({ request_type: groupSearch.dataset.requestType, q: term });
            const response = await fetch(`${groupAutocomplete.dataset.searchUrl}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin', signal: controller.signal,
            });
            const result = await response.json();
            if (!response.ok || !result.success || !Array.isArray(result.groups)) throw new Error('group_search_failed');
            renderGroupResults(result.groups);
        } catch (error) {
            if (error.name !== 'AbortError') {
                groupResults.replaceChildren();
                closeGroupResults();
                groupStatus.textContent = window.idolRankingT('group_search_error');
            }
        } finally {
            if (groupSearchController === controller) groupSearch.removeAttribute('aria-busy');
        }
    };

    groupSearch.addEventListener('input', () => {
        clearTimeout(groupSearchTimer);
        if (groupSearchController) groupSearchController.abort();
        clearGroupSelection(false);
        groupStatus.textContent = '';
        groupResults.replaceChildren();
        const term = groupSearch.value.trim();
        if (!term) return;
        clearTimeout(groupSearchTimer);
        groupSearchTimer = setTimeout(() => searchGroups(term), 275);
    });

    groupSearch.addEventListener('keydown', (event) => {
        const options = [...groupResults.querySelectorAll('[role="option"]')];
        if (event.key === 'Escape') {
            closeGroupResults();
            return;
        }
        if (!options.length || groupResults.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            groupActiveIndex = groupActiveIndex < 0
                ? (event.key === 'ArrowDown' ? 0 : options.length - 1)
                : (groupActiveIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === groupActiveIndex)));
            groupSearch.setAttribute('aria-activedescendant', options[groupActiveIndex].id);
            options[groupActiveIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && groupActiveIndex >= 0) {
            event.preventDefault();
            options[groupActiveIndex].click();
        }
    });

    noGroupButton.addEventListener('click', () => {
        clearTimeout(groupSearchTimer);
        if (groupSearchController) groupSearchController.abort();
        groupId.value = '';
        groupSearch.dataset.selectedGender = '';
        groupSearch.value = noGroupButton.textContent;
        groupResults.replaceChildren();
        groupStatus.textContent = window.idolRankingT('group_none_selected');
        closeGroupResults();
    });

    document.addEventListener('pointerdown', (event) => {
        if (!groupAutocomplete.contains(event.target)) closeGroupResults();
    });

    const intro = requestForm.querySelector('[name="content"]');
    const counter = requestForm.querySelector('[data-intro-count]');
    intro.addEventListener('input', () => { counter.textContent = `${intro.value.length}/1000`; });

    const englishNameInput = requestForm.querySelector('[name="english_name"]');
    const duplicateSearchButton = requestForm.querySelector('[data-duplicate-search]');
    const duplicateSearchToken = requestForm.querySelector('[data-duplicate-search-token]');
    const duplicatePanel = requestForm.querySelector('[data-duplicate-panel]');
    const registrationDetails = requestForm.querySelector('[data-registration-details]');
    const progressiveRequiredFields = requestForm.querySelectorAll('[data-progressive-required]');
    const duplicateStatus = requestForm.querySelector('[data-duplicate-status]');
    const duplicateStatusDescription = requestForm.querySelector('[data-duplicate-status-description]');
    const duplicateResults = requestForm.querySelector('[data-duplicate-candidates]');
    const otherCategorySection = requestForm.querySelector('[data-duplicate-other-section]');
    const otherCategoryResults = requestForm.querySelector('[data-duplicate-other-candidates]');
    const duplicateConfirm = requestForm.querySelector('[data-duplicate-confirm]');
    const duplicateConfirmed = requestForm.querySelector('[data-not-duplicate-confirmed]');
    const submitButton = requestForm.querySelector('button[type="submit"]');
    const duplicateSearchButtonText = duplicateSearchButton.textContent;
    let currentCandidates = [];
    let exactDuplicate = false;
    let duplicateSearchVersion = 0;
    let duplicateCheckComplete = false;
    let duplicateCheckFingerprint = '';

    const currentDuplicateFingerprint = () => {
        const category = typeInputs.find((input) => input.checked)?.value || '';
        return `${category}|${englishNameInput.value.trim()}`;
    };

    const setRegistrationDetailsVisible = (visible) => {
        registrationDetails.hidden = !visible;
        progressiveRequiredFields.forEach((field) => { field.disabled = !visible; });
    };

    const setDuplicateStatus = (state, messageKey, values = {}, descriptionKey = '') => {
        duplicatePanel.dataset.state = state;
        duplicatePanel.classList.remove('is-neutral', 'is-error', 'is-success', 'is-warning');
        duplicatePanel.classList.add(`is-${state}`);
        duplicatePanel.hidden = false;
        duplicateStatus.textContent = window.idolRankingT(messageKey, values);
        duplicateStatusDescription.textContent = descriptionKey ? window.idolRankingT(descriptionKey, values) : '';
        duplicateStatusDescription.hidden = !descriptionKey;
    };

    const clearDuplicateCheck = () => {
        duplicateSearchVersion += 1;
        setRegistrationDetailsVisible(false);
        duplicateSearchToken.value = '';
        duplicateConfirmed.checked = false;
        duplicateConfirm.hidden = true;
        duplicatePanel.hidden = false;
        duplicatePanel.dataset.state = 'neutral';
        duplicatePanel.classList.remove('is-error', 'is-success', 'is-warning');
        duplicatePanel.classList.add('is-neutral');
        duplicateStatus.textContent = window.idolRankingT('idol_duplicate_status_initial');
        duplicateStatusDescription.textContent = '';
        duplicateStatusDescription.hidden = true;
        duplicateResults.replaceChildren();
        otherCategoryResults.replaceChildren();
        otherCategorySection.hidden = true;
        duplicateSearchButton.disabled = false;
        duplicateSearchButton.textContent = duplicateSearchButtonText;
        submitButton.disabled = false;
        currentCandidates = [];
        exactDuplicate = false;
        duplicateCheckComplete = false;
        duplicateCheckFingerprint = '';
    };

    const renderCandidates = (candidates, container, showExact = true) => {
        const list = document.createElement('div');
        list.className = 'idol-duplicate-card-list';
        candidates.forEach((candidate) => {
            const card = document.createElement('article');
            card.className = 'idol-duplicate-card';
            const link = document.createElement('a');
            link.className = 'idol-duplicate-card-image';
            link.href = candidate.url;
            const image = document.createElement('img');
            image.src = candidate.image_url;
            image.alt = '';
            image.loading = 'lazy';
            link.append(image);
            const details = document.createElement('div');
            details.className = 'idol-duplicate-card-details';
            const category = document.createElement('small');
            category.textContent = candidate.category;
            const name = document.createElement('strong');
            name.textContent = candidate.name;
            name.setAttribute('translate', 'no');
            const englishName = document.createElement('span');
            englishName.textContent = candidate.english_name || '—';
            englishName.setAttribute('translate', 'no');
            details.append(category, name, englishName);
            if (candidate.group_name) {
                const group = document.createElement('span');
                group.textContent = window.idolRankingT('idol_duplicate_group', { name: candidate.group_name });
                group.setAttribute('translate', 'no');
                details.append(group);
            }
            if (showExact && candidate.exact) {
                const exact = document.createElement('small');
                exact.className = 'idol-duplicate-exact';
                exact.textContent = window.idolRankingT('idol_duplicate_exact_badge');
                details.append(exact);
            }
            card.append(link, details);
            list.append(card);
        });
        container.replaceChildren(list);
    };

    const searchDuplicates = async (restore = false) => {
        const englishName = englishNameInput.value.trim();
        if (englishName.length < 2) {
            setRegistrationDetailsVisible(false);
            setDuplicateStatus('error', 'idol_duplicate_enter_name');
            englishNameInput.focus();
            return;
        }

        const restoreConfirmation = restore && duplicateConfirmed.checked;
        const category = typeInputs.find((input) => input.checked)?.value || '';
        const searchVersion = ++duplicateSearchVersion;
        setRegistrationDetailsVisible(false);
        duplicateSearchButton.disabled = true;
        duplicateSearchButton.textContent = window.idolRankingT('idol_duplicate_searching');
        submitButton.disabled = true;
        duplicatePanel.hidden = true;
        duplicateResults.replaceChildren();
        otherCategoryResults.replaceChildren();
        otherCategorySection.hidden = true;
        duplicateConfirm.hidden = true;
        duplicateConfirmed.checked = restoreConfirmation;
        currentCandidates = [];
        exactDuplicate = false;
        duplicateCheckComplete = false;
        duplicateCheckFingerprint = '';

        try {
            const params = new URLSearchParams({ request_type: category, english_name: englishName });
            const response = await fetch(`${duplicateSearchButton.dataset.duplicateSearchUrl}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error('search_failed');
            if (searchVersion !== duplicateSearchVersion) return;

            duplicateSearchToken.value = result.token;
            duplicateCheckComplete = true;
            duplicateCheckFingerprint = currentDuplicateFingerprint();
            currentCandidates = result.candidates || [];
            const otherCandidates = result.other_candidates || [];
            exactDuplicate = result.exact_duplicate;
            if (otherCandidates.length) {
                renderCandidates(otherCandidates, otherCategoryResults, false);
                otherCategorySection.hidden = false;
            }
            if (currentCandidates.length) {
                renderCandidates(currentCandidates, duplicateResults);
                setDuplicateStatus(
                    exactDuplicate ? 'error' : 'warning',
                    exactDuplicate ? 'idol_duplicate_status_exact_heading' : 'idol_duplicate_status_similar_heading',
                    {},
                    exactDuplicate ? 'idol_duplicate_status_exact_description' : 'idol_duplicate_status_similar_description'
                );
                duplicateConfirm.hidden = exactDuplicate;
                if (exactDuplicate) duplicateConfirmed.checked = false;
                else if (duplicateConfirmed.checked) setRegistrationDetailsVisible(true);
            } else {
                duplicateConfirmed.checked = false;
                setDuplicateStatus('success', 'idol_duplicate_status_available', {}, 'idol_duplicate_status_available_description');
                setRegistrationDetailsVisible(true);
            }
        } catch {
            if (searchVersion !== duplicateSearchVersion) return;
            duplicateSearchToken.value = '';
            duplicateCheckComplete = false;
            duplicateCheckFingerprint = '';
            otherCategoryResults.replaceChildren();
            otherCategorySection.hidden = true;
            duplicateConfirmed.checked = false;
            setDuplicateStatus('error', 'idol_duplicate_status_error');
        } finally {
            if (searchVersion === duplicateSearchVersion) {
                duplicateSearchButton.disabled = false;
                duplicateSearchButton.textContent = duplicateSearchButtonText;
                submitButton.disabled = false;
            }
        }
    };

    duplicateSearchButton.addEventListener('click', () => searchDuplicates());

    duplicateConfirmed.addEventListener('change', () => {
        const confirmationIsCurrent = duplicateCheckComplete
            && duplicateCheckFingerprint === currentDuplicateFingerprint()
            && currentCandidates.length > 0
            && !exactDuplicate
            && duplicateConfirmed.checked;
        setRegistrationDetailsVisible(confirmationIsCurrent);
    });

    englishNameInput.addEventListener('input', clearDuplicateCheck);
    typeInputs.forEach((input) => input.addEventListener('change', clearDuplicateCheck));

    if (duplicateSearchToken.value) searchDuplicates(true);

    const crop = initSquareImageCrop({
        imageInput: requestForm.querySelector('[data-image-input]'),
        selection: requestForm.querySelector('[data-image-selection]'),
        preview: requestForm.querySelector('[data-image-preview]'),
        dialog: document.querySelector('[data-crop-dialog]'),
        openButton: requestForm.querySelector('[data-crop-open]'),
        allowedTypes: ['image/jpeg', 'image/png'],
        invalidMessage: 'Choose one JPG or PNG image up to 5MB.',
        outputName: 'idol-profile.png',
    });
    requestForm.querySelector('[data-image-remove]').addEventListener('click', crop.clear);
    requestForm.addEventListener('submit', (event) => {
        if (!duplicateCheckComplete || duplicateCheckFingerprint !== currentDuplicateFingerprint()) {
            event.preventDefault();
            searchDuplicates();
            return;
        }
        if (exactDuplicate) {
            event.preventDefault();
            setDuplicateStatus('error', 'idol_duplicate_status_exact_heading', {}, 'idol_duplicate_status_exact_description');
            return;
        }
        if (currentCandidates.length && !duplicateConfirmed.checked) {
            event.preventDefault();
            setDuplicateStatus('warning', 'idol_duplicate_status_similar_heading', {}, 'idol_duplicate_confirmation_required');
            duplicateConfirm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        const submit = requestForm.querySelector('button[type="submit"]');
        submit.disabled = true;
        submit.textContent = window.idolRankingT('submitting');
    });
}
