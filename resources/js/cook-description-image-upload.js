import {
    isCookDescriptionImageUpload,
    optimizeCookDescriptionImage,
} from './cook-description-image';

let optimizing = false;

function replaceInputFiles(input, file) {
    const transfer = new DataTransfer();

    if (file) {
        transfer.items.add(file);
    }

    input.files = transfer.files;
}

async function handleCookDescriptionImageSelection(event) {
    const input = event.target;

    if (! isCookDescriptionImageUpload(input) || ! input.files?.length) {
        return;
    }

    if (input.dataset.cookDescriptionImageReady === '1') {
        delete input.dataset.cookDescriptionImageReady;

        return;
    }

    if (optimizing) {
        return;
    }

    const original = input.files[0];

    event.preventDefault();
    event.stopImmediatePropagation();

    optimizing = true;

    try {
        const optimized = await optimizeCookDescriptionImage(original);
        replaceInputFiles(input, optimized);
        input.dataset.cookDescriptionImageReady = '1';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (error) {
        replaceInputFiles(input, null);

        const message = error instanceof Error
            ? error.message
            : 'This image could not be processed.';

        window.dispatchEvent(new CustomEvent('cook-description-image-error', {
            detail: { message },
        }));

        window.alert(message);
    } finally {
        optimizing = false;
    }
}

document.addEventListener('change', handleCookDescriptionImageSelection, true);
