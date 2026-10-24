export function initSquareImageCrop({
    imageInput,
    selection,
    preview,
    dialog,
    openButton = null,
    allowedTypes = ['image/jpeg', 'image/png', 'image/gif'],
    invalidMessage = 'Choose one JPG, PNG, or GIF image up to 5MB.',
    outputName = 'cropped-image.png',
    existingImage = '',
    onSelect = () => {},
    onClear = () => {},
}) {
    const canvas = dialog.querySelector('[data-crop-canvas]');
    const stage = dialog.querySelector('[data-crop-stage]');
    const zoomInput = dialog.querySelector('[data-crop-zoom]');
    const context = canvas.getContext('2d');
    const originalImage = new Image();
    const pointers = new Map();
    let originalFile = null;
    let originalSourceUrl = null;
    let existingSourceUrl = existingImage;
    let previewUrl = null;
    let croppedFile = null;
    let appliedCropState = null;
    let scale = 1;
    let minScale = 1;
    let maxScale = 3;
    let offsetX = 0;
    let offsetY = 0;
    let pinchDistance = null;
    let stageWidth = 0;
    let stageHeight = 0;
    let workspaceReady = false;

    const clampScale = (value) => {
        const numericValue = Number(value);
        return Math.max(minScale, Math.min(maxScale, Number.isFinite(numericValue) ? numericValue : minScale));
    };
    const setZoomValue = (value) => {
        scale = clampScale(value);
        zoomInput.value = String(scale);
        // A range input may normalize a floating-point value to its configured step.
        scale = clampScale(zoomInput.valueAsNumber);
        zoomInput.value = String(scale);
    };

    const draw = () => {
        const rect = stage.getBoundingClientRect();
        if (!rect.width || !rect.height || !originalImage.naturalWidth || !originalImage.naturalHeight) return;
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.round(rect.width * ratio);
        canvas.height = Math.round(rect.height * ratio);
        const width = originalImage.naturalWidth * scale * ratio;
        const height = originalImage.naturalHeight * scale * ratio;
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(originalImage, (canvas.width - width) / 2 + offsetX * ratio, (canvas.height - height) / 2 + offsetY * ratio, width, height);
    };
    const configureZoom = (view = null) => {
        const rect = stage.getBoundingClientRect();
        if (!rect.width || !rect.height || !originalImage.naturalWidth || !originalImage.naturalHeight) return false;
        const zoomRatio = view?.zoomRatio ?? appliedCropState?.zoomRatio;
        const offsetXRatio = view?.offsetXRatio ?? appliedCropState?.offsetXRatio;
        const offsetYRatio = view?.offsetYRatio ?? appliedCropState?.offsetYRatio;
        const cropSize = Math.min(rect.width, rect.height) * .76;
        // Contain scale fits the complete source inside the visible square crop box.
        minScale = Math.min(cropSize / originalImage.naturalWidth, cropSize / originalImage.naturalHeight);
        const coverScale = Math.max(cropSize / originalImage.naturalWidth, cropSize / originalImage.naturalHeight);
        // Keep the calculated contain scale as the true minimum while allowing a generous tighter crop.
        maxScale = Math.max(minScale * 3, coverScale * 3);
        const initialScale = Math.min((cropSize + 2) / originalImage.naturalWidth, (cropSize + 2) / originalImage.naturalHeight);
        // Re-edit from the original source and the last applied transform, never from the cropped output.
        zoomInput.min = String(minScale);
        zoomInput.max = String(maxScale);
        zoomInput.step = String(Math.max((maxScale - minScale) / 200, .0001));
        setZoomValue(Number.isFinite(zoomRatio) ? minScale + zoomRatio * (maxScale - minScale) : initialScale);
        offsetX = Number.isFinite(offsetXRatio) ? offsetXRatio * rect.width : 0;
        offsetY = Number.isFinite(offsetYRatio) ? offsetYRatio * rect.height : 0;
        stageWidth = rect.width;
        stageHeight = rect.height;
        return true;
    };
    const resizeWorkspace = () => {
        if (!workspaceReady || !dialog.open || !originalImage.naturalWidth) return;
        const rect = stage.getBoundingClientRect();
        if (!rect.width || !rect.height || (Math.abs(rect.width - stageWidth) < .5 && Math.abs(rect.height - stageHeight) < .5)) return;
        const view = {
            zoomRatio: maxScale > minScale ? (scale - minScale) / (maxScale - minScale) : 0,
            offsetXRatio: stageWidth ? offsetX / stageWidth : 0,
            offsetYRatio: stageHeight ? offsetY / stageHeight : 0,
        };
        if (configureZoom(view)) draw();
    };
    if ('ResizeObserver' in window) {
        new ResizeObserver(resizeWorkspace).observe(stage);
    } else {
        window.addEventListener('resize', resizeWorkspace);
    }
    const showDialog = () => {
        dialog.showModal();
        document.documentElement.classList.add('fan-post-crop-open');
        requestAnimationFrame(() => {
            if (!dialog.open) return;
            workspaceReady = configureZoom();
            if (workspaceReady) draw();
        });
    };
    const open = () => {
        const source = originalSourceUrl || existingSourceUrl;
        if (!source) return;
        if (originalImage.src === source && originalImage.complete) showDialog();
        else {
            originalImage.onload = showDialog;
            if (!originalSourceUrl) originalImage.crossOrigin = 'anonymous';
            originalImage.src = source;
        }
    };
    const clear = () => {
        imageInput.value = '';
        selection.hidden = true;
        if (originalSourceUrl) URL.revokeObjectURL(originalSourceUrl);
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        originalFile = null;
        originalSourceUrl = null;
        existingSourceUrl = '';
        previewUrl = null;
        croppedFile = null;
        appliedCropState = null;
        preview.removeAttribute('src');
        originalImage.removeAttribute('src');
        onClear();
    };

    imageInput.addEventListener('change', () => {
        const file = imageInput.files[0];
        if (!file) return;
        if (!allowedTypes.includes(file.type) || file.size > 5 * 1024 * 1024) {
            window.alert(invalidMessage);
            clear();
            return;
        }
        if (originalSourceUrl) URL.revokeObjectURL(originalSourceUrl);
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        originalFile = file;
        originalSourceUrl = URL.createObjectURL(originalFile);
        existingSourceUrl = '';
        croppedFile = null;
        appliedCropState = null;
        previewUrl = URL.createObjectURL(originalFile);
        preview.src = previewUrl;
        selection.hidden = false;
        onSelect();
    });
    openButton?.addEventListener('click', open);
    dialog.querySelectorAll('[data-crop-cancel]').forEach((button) => {
        button.addEventListener('click', () => dialog.close('cancel'));
    });
    dialog.addEventListener('close', () => {
        workspaceReady = false;
        document.documentElement.classList.remove('fan-post-crop-open');
        originalImage.removeAttribute('src');
    });
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close('cancel'); });
    zoomInput.addEventListener('input', () => { setZoomValue(zoomInput.valueAsNumber); draw(); });
    dialog.querySelector('[data-zoom-out]').addEventListener('click', () => {
        const increment = (maxScale - minScale) / 20;
        setZoomValue(scale - increment);
        zoomInput.dispatchEvent(new Event('input'));
    });
    dialog.querySelector('[data-zoom-in]').addEventListener('click', () => {
        const increment = (maxScale - minScale) / 20;
        setZoomValue(scale + increment);
        zoomInput.dispatchEvent(new Event('input'));
    });
    stage.addEventListener('pointerdown', (event) => { stage.setPointerCapture(event.pointerId); pointers.set(event.pointerId, { x: event.clientX, y: event.clientY }); });
    stage.addEventListener('pointermove', (event) => {
        if (!pointers.has(event.pointerId)) return;
        const previous = pointers.get(event.pointerId);
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        if (pointers.size === 1) {
            offsetX += event.clientX - previous.x;
            offsetY += event.clientY - previous.y;
        } else if (pointers.size === 2) {
            const [a, b] = [...pointers.values()];
            const distance = Math.hypot(a.x - b.x, a.y - b.y);
            if (pinchDistance) setZoomValue(scale * distance / pinchDistance);
            pinchDistance = distance;
        }
        draw();
    });
    const endPointer = (event) => { pointers.delete(event.pointerId); if (pointers.size < 2) pinchDistance = null; };
    stage.addEventListener('pointerup', endPointer);
    stage.addEventListener('pointercancel', endPointer);
    dialog.querySelector('[data-crop-apply]').addEventListener('click', () => {
        const cropX = Math.round(canvas.width * .12);
        const cropY = Math.round(canvas.height * .12);
        const cropSize = Math.round(Math.min(canvas.width, canvas.height) * .76);
        const output = document.createElement('canvas');
        output.width = Math.min(1600, cropSize);
        output.height = output.width;
        const outputContext = output.getContext('2d');
        // Export the exact visible square, including white space left by contain-scale images.
        outputContext.fillStyle = '#fff';
        outputContext.fillRect(0, 0, output.width, output.height);
        outputContext.drawImage(canvas, cropX, cropY, cropSize, cropSize, 0, 0, output.width, output.height);
        output.toBlob((blob) => {
            croppedFile = new File([blob], outputName, { type: 'image/png' });
            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            imageInput.files = transfer.files;
            appliedCropState = {
                zoomRatio: maxScale > minScale ? (scale - minScale) / (maxScale - minScale) : 0,
                offsetXRatio: stageWidth ? offsetX / stageWidth : 0,
                offsetYRatio: stageHeight ? offsetY / stageHeight : 0,
            };
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(blob);
            preview.src = previewUrl;
            selection.hidden = false;
            dialog.close('apply');
        }, 'image/png', .92);
    });
    return { clear, open };
}
