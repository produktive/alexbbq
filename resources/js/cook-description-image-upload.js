import {
    isCookDescriptionImageInput,
    optimizeCookDescriptionImage,
} from './cook-description-image';

const HOOK_POLL_MS = 16;

let optimizing = false;
let hookPollIntervalId = null;

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

function wrapCookDescriptionServerProcess(originalProcess) {
    return (fieldName, file, metadata, load, error, progress, abort) => {
        if (! (file instanceof File) || ! file.type.startsWith('image/')) {
            return originalProcess(fieldName, file, metadata, load, error, progress, abort);
        }

        let abortHandle = null;

        optimizeCookDescriptionImage(file)
            .then((optimized) => {
                abortHandle = originalProcess(
                    fieldName,
                    optimized,
                    metadata,
                    load,
                    error,
                    progress,
                    abort,
                );
            })
            .catch((caught) => {
                showCookDescriptionImageError(caught);
                error(caught instanceof Error ? caught.message : 'This image could not be processed.');
            });

        return {
            abort: () => {
                abortHandle?.abort?.();
            },
        };
    };
}

function installFilePondCreateHook() {
    const { FilePond } = window;

    if (! FilePond?.create || FilePond.create.__cookDescriptionHook) {
        return Boolean(FilePond?.create?.__cookDescriptionHook);
    }

    const originalCreate = FilePond.create.bind(FilePond);

    function wrappedCreate(input, options) {
        if (isCookDescriptionImageInput(input) && options?.server?.process) {
            options.server.process = wrapCookDescriptionServerProcess(
                options.server.process,
            );

            input.dataset.cookDescriptionImageProcessHooked = '1';
        }

        return originalCreate(input, options);
    }

    wrappedCreate.__cookDescriptionHook = true;
    FilePond.create = wrappedCreate;

    return true;
}

function stopHookPolling() {
    if (hookPollIntervalId === null) {
        return;
    }

    window.clearInterval(hookPollIntervalId);
    hookPollIntervalId = null;
}

function startHookPolling() {
    if (hookPollIntervalId !== null || installFilePondCreateHook()) {
        return;
    }

    hookPollIntervalId = window.setInterval(() => {
        if (installFilePondCreateHook()) {
            stopHookPolling();
        }
    }, HOOK_POLL_MS);
}

function scheduleCookDescriptionUploadHooks() {
    if (! document.querySelector('[data-cook-description-image-upload]')) {
        stopHookPolling();

        return;
    }

    if (! installFilePondCreateHook()) {
        startHookPolling();
    }
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
    if (! window.__cookDescriptionUploadObserver) {
        window.__cookDescriptionUploadObserver = new MutationObserver(() => {
            scheduleCookDescriptionUploadHooks();
        });

        window.__cookDescriptionUploadObserver.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }

    scheduleCookDescriptionUploadHooks();
}

initCookDescriptionImageUploadHooks();

document.addEventListener('change', handleCookDescriptionImageSelection, true);
document.addEventListener('livewire:navigated', scheduleCookDescriptionUploadHooks);
