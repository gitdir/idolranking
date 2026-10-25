import { initSquareImageCrop } from './square-image-crop';

const form = document.querySelector('[data-quiz-create] [data-quiz-form]');

if (form) {
    const root = form.closest('[data-quiz-create]');
    const categoryOptions = root.querySelector('[data-idol-category-options]');
    const categoryInputs = [...categoryOptions.querySelectorAll('[name="idol_category"]')];
    const idolSelect = root.querySelector('[data-idol-select]');
    let panels = [...root.querySelectorAll('[data-question-panel]')];
    let tabs = [...root.querySelectorAll('[data-question-tab]')];
    let activeCount = panels.filter((panel) => !panel.hidden).length;
    let activeIndex = 0;

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
    const loadIdolCategory = async (category, selectedIdol = '') => {
        categoryRequest?.abort();
        categoryRequest = new AbortController();
        idolSelect.disabled = true;
        const placeholder = idolSelect.options[0];
        placeholder.textContent = root.dataset.idolLoading;
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
            setIdolOptions(data.idols ?? [], selectedIdol);
            idolSelect.disabled = (data.idols ?? []).length === 0;
            placeholder.textContent = idolSelect.disabled ? root.dataset.idolNoneAvailable : root.dataset.idolChooseOption;
        } catch (error) {
            if (error.name !== 'AbortError') {
                placeholder.textContent = root.dataset.idolOptionsUnavailable;
                console.error(error);
            }
        }
    };
    categoryInputs.forEach((input) => input.addEventListener('change', () => {
        if (!input.checked) return;
        idolSelect.value = '';
        loadIdolCategory(input.value);
    }));
    const initiallySelectedCategory = categoryInputs.find((input) => input.checked);
    if (initiallySelectedCategory) loadIdolCategory(initiallySelectedCategory.value, idolSelect.value);

    const coverSelection = root.querySelector('[data-cover-selection]');
    const coverImageInput = root.querySelector('[data-cover-image]');
    const coverFileName = root.querySelector('[data-cover-filename]');
    const removeCoverImage = root.querySelector('[data-remove-cover-image]');
    const coverCrop = initSquareImageCrop({
        imageInput: coverImageInput,
        selection: coverSelection,
        preview: coverSelection.querySelector('[data-cover-preview]'),
        dialog: root.querySelector('#quiz-cover-crop-dialog'),
        openButton: coverSelection.querySelector('[data-crop-open]'),
        allowedTypes: ['image/jpeg', 'image/png', 'image/webp'],
        invalidMessage: root.dataset.imageInvalidMessage,
        outputName: 'quiz-cover.png',
        existingImage: coverSelection.dataset.existingImage || '',
        onSelect: () => {
            coverFileName.textContent = coverImageInput.files[0]?.name ?? root.dataset.coverNoFile;
            removeCoverImage.value = '0';
        },
        onClear: () => {
            coverFileName.textContent = root.dataset.coverNoFile;
            removeCoverImage.value = '1';
        },
    });
    coverSelection.querySelector('[data-image-remove]').addEventListener('click', coverCrop.clear);
    let questionCrops = panels.map((panel, index) => {
        const selection = panel.querySelector('[data-question-image-selection]');
        const existingQuestionInput = panel.querySelector('[data-existing-question-idx]');
        const crop = initSquareImageCrop({
            imageInput: panel.querySelector('[data-question-image]'),
            selection,
            preview: selection.querySelector('[data-question-image-preview]'),
            dialog: root.querySelector(`#quiz-question-crop-dialog-${index}`),
            openButton: selection.querySelector('[data-crop-open]'),
            allowedTypes: ['image/jpeg', 'image/png', 'image/webp'],
            invalidMessage: root.dataset.imageInvalidMessage,
            outputName: `quiz-question-${index + 1}.png`,
            existingImage: selection.dataset.existingImage || '',
            onSelect: () => { existingQuestionInput.value = ''; },
            onClear: () => { existingQuestionInput.value = ''; },
        });
        selection.querySelector('[data-image-remove]').addEventListener('click', crop.clear);
        return crop;
    });

    const setPanelEnabled = (panel, enabled) => {
        panel.querySelectorAll('input, textarea, select').forEach((control) => {
            if (control.matches('[data-question-prompt], [data-question-type]')) {
                control.disabled = !enabled;
                return;
            }

            const typeFields = control.closest('[data-type-fields]');
            const typeEnabled = !typeFields || typeFields.dataset.typeFields.split(' ').includes(panel.querySelector('[data-question-type]').value);
            const imageField = control.closest('[data-image-field]');
            const imageEnabled = !imageField || panel.querySelector('[data-question-type]').value === 'image';
            control.disabled = !enabled || !typeEnabled || !imageEnabled;
        });
    };

    const updateCorrectChoice = (panel) => {
        panel.querySelectorAll('[data-correct-choice]').forEach((input) => {
            const row = input.closest('.quiz-choice-row');
            const selected = input.checked;
            row?.classList.toggle('is-correct', selected);
            const marker = row?.querySelector('.quiz-correct-marker');
            if (marker) marker.hidden = !selected;
        });
    };

    const updatePanel = (index) => {
        const panel = panels[index];
        if (!panel) return;
        const type = panel.querySelector('[data-question-type]').value;
        panel.querySelectorAll('[data-select-type]').forEach((button) => {
            const selected = button.dataset.selectType === type;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        panel.querySelectorAll('[data-type-fields]').forEach((fields) => {
            fields.hidden = !fields.dataset.typeFields.split(' ').includes(type);
        });
        const imageField = panel.querySelector('[data-image-field]');
        if (imageField) imageField.hidden = type !== 'image';
        setPanelEnabled(panel, index < visibleCount());
    };

    const visibleCount = () => activeCount;

    const showPanel = (index) => {
        const activePanel = panels[index];
        if (!activePanel) return;

        activeIndex = index;
        panels.forEach((panel, panelIndex) => {
            const visible = panelIndex < visibleCount() && panelIndex === index;
            panel.hidden = !visible;
            setPanelEnabled(panel, panelIndex < visibleCount());
        });
        tabs.forEach((tab, tabIndex) => {
            tab.hidden = tabIndex >= visibleCount();
            tab.setAttribute('aria-selected', tabIndex === index ? 'true' : 'false');
            tab.classList.toggle('is-active', tabIndex === index);
        });
        const addButton = root.querySelector('[data-add-question]');
        if (addButton) addButton.disabled = activeCount >= 3;
        root.querySelectorAll('[data-delete-question]').forEach((button) => {
            button.disabled = activeCount <= 1;
        });
        const countBadge = root.querySelector('[data-question-count]');
        if (countBadge) countBadge.textContent = root.dataset.questionCountLabel.replace(':current', String(activeCount));
        updatePanel(index);
        updateCorrectChoice(activePanel);
    };

    const reindex = () => {
        panels.forEach((panel, index) => {
            panel.dataset.questionPanel = index;
            panel.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/questions\[\d+\]/, `questions[${index}]`);
            });
            const tab = tabs[index];
            tab.dataset.questionTab = index;
            tab.textContent = root.dataset.questionLabel.replace(':number', String(index + 1));
            tab.id = `quiz-question-tab-${index}`;
            tab.setAttribute('aria-controls', `quiz-question-panel-${index}`);
            panel.id = `quiz-question-panel-${index}`;
            panel.setAttribute('aria-labelledby', `quiz-question-tab-${index}`);
        });
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => showPanel(Number(tab.dataset.questionTab))));
    panels.forEach((panel) => {
        panel.querySelectorAll('[data-select-type]').forEach((button) => button.addEventListener('click', () => {
            panel.querySelector('[data-question-type]').value = button.dataset.selectType;
            updatePanel(Number(panel.dataset.questionPanel));
        }));
        panel.addEventListener('change', (event) => {
            if (event.target.matches('[data-correct-choice]')) updateCorrectChoice(panel);
        });
    });

    root.querySelector('[data-add-question]')?.addEventListener('click', () => {
        const nextIndex = visibleCount();
        if (nextIndex >= 3) return;
        const panel = panels[nextIndex];
        questionCrops[nextIndex].clear();
        panel.hidden = false;
        activeCount++;
        panel.querySelectorAll('input, textarea, select').forEach((field) => {
            if (field.matches('[type="file"]')) field.value = '';
            else if (field.matches('[type="radio"]')) field.checked = false;
            else field.value = '';
        });
        panel.querySelectorAll('[data-question-type]').forEach((field) => { field.value = 'choice'; });
        reindex();
        showPanel(nextIndex);
    });

    root.querySelectorAll('[data-delete-question]').forEach((button) => button.addEventListener('click', () => {
        const count = visibleCount();
        if (count <= 1) return;
        const removedPanel = panels.splice(activeIndex, 1)[0];
        const removedTab = tabs.splice(activeIndex, 1)[0];
        const removedCrop = questionCrops.splice(activeIndex, 1)[0];
        removedCrop.clear();
        panels.push(removedPanel);
        tabs.push(removedTab);
        root.querySelector('[data-question-panels]').append(removedPanel);
        root.querySelector('[data-question-tabs]').append(removedTab);
        activeCount--;
        removedPanel.hidden = true;
        removedPanel.querySelectorAll('input, textarea, select').forEach((field) => {
            field.disabled = true;
            if (field.matches('[type="file"]')) field.value = '';
            else if (field.matches('[type="radio"]')) field.checked = false;
            else field.value = '';
        });
        removedPanel.querySelector('[data-question-type]').value = '';
        reindex();
        showPanel(Math.min(activeIndex, count - 2));
    }));

    const isValid = (showAlert) => {
        const activePanels = panels.slice(0, activeCount);
        let firstInvalid = categoryInputs.some((input) => input.checked) ? null : categoryInputs[0];
        if (!firstInvalid && !idolSelect.value) firstInvalid = idolSelect;
        activePanels.forEach((panel) => {
            const type = panel.querySelector('[data-question-type]').value;
            const prompt = panel.querySelector('[data-question-prompt]');
            if (!type || !prompt.value.trim()) firstInvalid ??= !type ? panel.querySelector('[data-select-type]') : prompt;
            if (type === 'image') {
                const image = panel.querySelector('[data-question-image]');
                const existingImageIdx = panel.querySelector('[data-existing-question-idx]')?.value;
                if (!image.files.length && !existingImageIdx) firstInvalid ??= image;
            }
            if (type === 'image' || type === 'choice') {
                const choices = [...panel.querySelectorAll('[data-question-choice]')];
                if (choices.length !== 5 || choices.some((choice) => !choice.value.trim())) firstInvalid ??= choices.find((choice) => !choice.value.trim()) || choices[0];
                if (!panel.querySelector('[data-correct-choice]:checked')) firstInvalid ??= panel.querySelector('[data-correct-choice]');
            }
            if (type === 'ox' && !panel.querySelector('[name$="[correct_answer]"]:checked')) firstInvalid ??= panel.querySelector('[name$="[correct_answer]"]');
        });
        if (firstInvalid && showAlert) {
            window.alert(root.dataset.validationMessage);
            const invalidPanel = firstInvalid.closest('[data-question-panel]');
            if (invalidPanel) showPanel(Number(invalidPanel.dataset.questionPanel));
            firstInvalid.focus?.();
        }
        return !firstInvalid;
    };

    form.addEventListener('submit', (event) => {
        if (!isValid(true)) event.preventDefault();
    });

    showPanel(0);
}

document.querySelector('[data-quiz-list-sort]')?.addEventListener('change', (event) => {
    event.currentTarget.form?.requestSubmit();
});

const quizListFilters = document.querySelector('[data-quiz-list-filters]');

if (quizListFilters) {
    const validCategories = ['boy', 'boy_group', 'girl', 'girl_group'];
    const categoryButtons = [...quizListFilters.querySelectorAll('[data-idol-category]')];
    const categoryInputs = [...document.querySelectorAll('[data-idol-category-state]')];
    const resultHeading = document.querySelector('[data-quiz-category-heading]');
    let category = validCategories.includes(quizListFilters.dataset.selectedCategory)
        ? quizListFilters.dataset.selectedCategory
        : '';

    const syncFilterState = () => {
        categoryInputs.forEach((input) => { input.value = category; });

        const url = new URL(window.location.href);
        if (category) url.searchParams.set('idol_category', category);
        else url.searchParams.delete('idol_category');
        url.searchParams.delete('idol_idx');
        window.history.replaceState({}, '', url);

        document.querySelectorAll('.quiz-list-pagination a').forEach((link) => {
            const linkUrl = new URL(link.href, window.location.href);
            if (category) linkUrl.searchParams.set('idol_category', category);
            else linkUrl.searchParams.delete('idol_category');
            linkUrl.searchParams.delete('idol_idx');
            link.href = linkUrl.toString();
        });

        if (resultHeading) {
            if (!category) {
                resultHeading.textContent = resultHeading.dataset.allHeading;
            } else {
                const selectedButton = categoryButtons.find((button) => button.dataset.idolCategory === category);
                resultHeading.textContent = resultHeading.dataset.categoryTemplate
                    .replace(':category', selectedButton?.dataset.categoryLabel || '')
                    .replace(':count', resultHeading.dataset.resultCount || '0');
            }
        }
    };

    const setActiveCategory = () => {
        categoryButtons.forEach((button) => {
            const active = button.dataset.idolCategory === category;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    categoryButtons.forEach((button) => button.addEventListener('click', () => {
        const nextCategory = button.dataset.idolCategory;
        if (nextCategory === category) return;
        category = validCategories.includes(nextCategory) ? nextCategory : '';
        setActiveCategory();
        const url = new URL(window.location.href);
        if (category) url.searchParams.set('idol_category', category);
        else url.searchParams.delete('idol_category');
        url.searchParams.delete('page');
        window.location.assign(url.toString());
    }));

    setActiveCategory();
    syncFilterState();
}

const quizDetail = document.querySelector('[data-quiz-detail]');

if (quizDetail) {
    const questionCards = [...quizDetail.querySelectorAll('[data-quiz-question]')];
    const questionForms = [...quizDetail.querySelectorAll('[data-quiz-answer-form]')];
    const answers = new Map();
    let currentQuestionPosition = 1;
    let hasStarted = false;
    let isSubmitting = false;

    const beforeUnloadHandler = (event) => {
        if (!hasStarted || isSubmitting) return;
        event.preventDefault();
        event.returnValue = '';
    };

    const showQuestion = (order) => {
        currentQuestionPosition = order;
        questionCards.forEach((card) => {
            card.hidden = Number(card.dataset.quizQuestion) !== currentQuestionPosition;
        });
    };

    quizDetail.addEventListener('change', (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.name !== 'selected_choice_order' || !input.checked) return;

        const form = input.closest('[data-quiz-answer-form]');
        if (!form) return;
        answers.set(Number(form.dataset.questionOrder), input.value);

        if (!hasStarted) {
            hasStarted = true;
            window.addEventListener('beforeunload', beforeUnloadHandler);
        }
    });

    quizDetail.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const previousButton = target?.closest('[data-quiz-previous]');
        const nextButton = target?.closest('[data-quiz-next]');

        if (previousButton) showQuestion(Number(previousButton.dataset.quizPrevious) - 1);
        if (nextButton) showQuestion(Number(nextButton.dataset.quizNext) + 1);
    });

    questionForms.forEach((form) => form.addEventListener('submit', (event) => {
        const selections = questionForms.map((questionForm) => ({
            order: Number(questionForm.dataset.questionOrder),
            selected: answers.get(Number(questionForm.dataset.questionOrder)),
            form: questionForm,
        }));
        const unanswered = selections.find((answer) => !answer.selected);

        if (unanswered) {
            event.preventDefault();
            showQuestion(unanswered.order);
            unanswered.form.querySelector('[name="selected_choice_order"]')?.reportValidity();
            return;
        }

        form.querySelectorAll('[data-quiz-answers-field]').forEach((input) => input.remove());
        selections.forEach(({ order, selected }) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `answers[${order}]`;
            input.value = selected;
            input.dataset.quizAnswersField = '';
            form.append(input);
        });
        isSubmitting = true;
        window.removeEventListener('beforeunload', beforeUnloadHandler);
    }));
}

const quizLikeButton = document.querySelector('[data-quiz-like]');
if (quizLikeButton) {
    const likeCount = quizLikeButton.querySelector('[data-quiz-like-count]');
    const countFormatter = new Intl.NumberFormat(document.documentElement.lang || undefined);
    let isLiked = quizLikeButton.dataset.liked === 'true';
    let isSavingLike = false;

    quizLikeButton.addEventListener('click', async () => {
        if (quizLikeButton.dataset.signedIn !== 'true') {
            document.dispatchEvent(new CustomEvent('idolranking:sign-in'));
            return;
        }
        if (isSavingLike) return;

        isSavingLike = true;
        quizLikeButton.disabled = true;
        try {
            const payload = { liked: !isLiked };
            if (quizLikeButton.dataset.requestKey) {
                payload.request_key = quizLikeButton.dataset.requestKey;
                payload.quiz_version = Number(quizLikeButton.dataset.quizVersion);
            }
            const response = await fetch(quizLikeButton.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': quizLikeButton.dataset.csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok || !result.success) return;

            isLiked = Boolean(result.data.liked);
            quizLikeButton.dataset.liked = String(isLiked);
            quizLikeButton.setAttribute('aria-pressed', String(isLiked));
            quizLikeButton.classList.toggle('is-liked', isLiked);
            likeCount.textContent = countFormatter.format(result.data.count);
        } catch (error) {
            console.error('Quiz Like request failed.', error);
        } finally {
            isSavingLike = false;
            quizLikeButton.disabled = false;
        }
    });
}
