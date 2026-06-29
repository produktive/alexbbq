import {
    isCookDescriptionImageInput,
    optimizeCookDescriptionImage,
} from './cook-description-image';

const HOOK_RETRY_MS = 100;
const HOOK_RETRY_ATTEMPTS = 100;

let optimizing = false;

function showCookDescriptionImageError(error) {
    const message = error instanceof Error
        ? error.message
        : 'This image could not be processed.';

    window.dispatchEvent(new CustomEvent('cook-description-image-error', {
        detail: { message },
    }));

    window.alert(message);
}

function replaceInputFiles(input, file) {
    const transfer = new DataTransfer();

    if (file) {
        transfer.items.add(file);
    }

    input.files = transfer.files;
}

function replaceFileItemFile(fileItem, file) {
    fileItem.file = file;

    if ('source' in fileItem) {
        fileItem.source = file;
    }
}

async function optimizeCookDescriptionFileItem(fileItem) {
    if (! (fileItem?.file instanceof File)) {
        return;
    }

    const optimized = await optimizeCookDescriptionImage(fileItem.file);

    if (optimized !== fileItem.file) {
        replaceFileItemFile(fileItem, optimized);
    }
}

function chainBeforeAddFile(original, hook) {
    return async (fileItem) => {
        try {
            await hook(fileItem);
        } catch (error) {
            showCookDescriptionImageError(error);

            return false;
        }

        if (typeof original === 'function') {
            return await original(fileItem);
        }

        return true;
    };
}

function installFilePondCreateHook() {
    const { FilePond } = window;

    if (! FilePond?.create || FilePond.create.__cookDescriptionHook) {
        return false;
    }

    const originalCreate = FilePond.create.bind(FilePond);

    function wrappedCreate(input, options) {
        if (isCookDescriptionImageInput(input) && options != null) {
            options.beforeAddFile = chainBeforeAddFile(
                options.beforeAddFile,
                optimizeCookDescriptionFileItem,
            );

            input.dataset.cookDescriptionImagePondHooked = '1';
        }

        return originalCreate(input, options);
    }

    wrappedCreate.__cookDescriptionHook = true;
    FilePond.create = wrappedCreate;

    return true;
}

function hookExistingCookDescriptionPond(input) {
    if (input.dataset.cookDescriptionImagePondHooked === '1') {
        return true;
    }

    const { FilePond } = window;

    if (! FilePond?.find) {
        return false;
    }

    const pond = FilePond.find(input);

    if (! pond) {
        return false;
    }

    pond.setOptions({
        beforeAddFile: chainBeforeAddFile(
            async () => true,
            optimizeCookDescriptionFileItem,
        ),
    });

    input.dataset.cookDescriptionImagePondHooked = '1';

    return true;
}

function retryUntil(callback, intervalMs, maxAttempts) {
    let attempts = 0;

    const intervalId = window.setInterval(() => {
        if (callback() || ++attempts >= maxAttempts) {
            window.clearInterval(intervalId);
        }
    }, intervalMs);
}

function scheduleCookDescriptionUploadHooks() {
    installFilePondCreateHook();

    document.querySelectorAll('[data-cook-description-image-upload] input[type="file"]').forEach((input) => {
        if (input.dataset.cookDescriptionImagePondHooked === '1') {
            return;
        }

        if (! hookExistingCookDescriptionPond(input)) {
            retryUntil(
                () => hookExistingCookDescriptionPond(input),
                HOOK_RETRY_MS,
                HOOK_RETRY_ATTEMPTS,
            );
        }
    });
}

function interceptFilePondAssignment() {
    if (window.__cookDescriptionFilePondIntercept) {
        return;
    }

    window.__cookDescriptionFilePondIntercept = true;

    let filePond = window.FilePond;

    try {
        Object.defineProperty(window, 'FilePond', {
            configurable: true,
            enumerable: true,
            get() {
                return filePond;
            },
            set(value) {
                filePond = value;
                scheduleCookDescriptionUploadHooks();
            },
        });
    } catch {
        // FilePond may already be defined; polling below still applies.
    }

    scheduleCookDescriptionUploadHooks();
}

async function handleCookDescriptionImageSelection(event) {
    const input = event.target;

    if (! isCookDescriptionImageInput(input) || ! input.files?.length) {
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
        showCookDescriptionImageError(error);
    } finally {
        optimizing = false;
    }
}

function initCookDescriptionImageUploadHooks() {
    interceptFilePondAssignment();

    if (! window.__cookDescriptionUploadObserver) {
        window.__cookDescriptionUploadObserver = new MutationObserver(() => {
            scheduleCookDescriptionUploadHooks();
        });

        window.__cookDescriptionUploadObserver.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }
}

initCookDescriptionImageUploadHooks();

document.addEventListener('change', handleCookDescriptionImageSelection, true);
document.addEventListener('livewire:navigated', scheduleCookDescriptionUploadHooks);
