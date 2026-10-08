import { initSquareImageCrop } from './square-image-crop';

const requestForm = document.querySelector('[data-idol-request-form]');

if (requestForm) {
    const typeInputs = [...requestForm.querySelectorAll('[name="request_type"]')];
    const groupSelect = requestForm.querySelector('[data-group-field] select');
    const individualFields = [...requestForm.querySelectorAll('[data-individual-only]')];
    const nationalityLabel = requestForm.querySelector('[data-nationality-label]');
    const nationalityInput = requestForm.querySelector('[data-nationality-input]');
    const updateTypeFields = () => {
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
        nationalityLabel.textContent = isIndividual ? 'Nationality' : 'Country';
        nationalityInput.placeholder = isIndividual ? 'e.g. South Korean' : 'e.g. South Korea';
        [...groupSelect.options].forEach((option) => {
            if (!option.value) return;
            option.hidden = option.dataset.gender !== gender;
            if (option.selected && option.hidden) groupSelect.value = '';
        });
    };
    typeInputs.forEach((input) => input.addEventListener('change', updateTypeFields));
    updateTypeFields();

    const intro = requestForm.querySelector('[name="content"]');
    const counter = requestForm.querySelector('[data-intro-count]');
    intro.addEventListener('input', () => { counter.textContent = `${intro.value.length}/1000`; });

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
    requestForm.addEventListener('submit', () => {
        const submit = requestForm.querySelector('button[type="submit"]');
        submit.disabled = true;
        submit.textContent = 'Submitting...';
    });
}
