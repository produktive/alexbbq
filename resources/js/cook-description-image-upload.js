import {
    isCookDescriptionImageInput,
    optimizeCookDescriptionImage,
} from './cook-description-image';

const HOOK_POLL_MS = 16;
const HOOK_POLL_MAX_ATTEMPTS = 600;

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

function isCookDescriptionPond(query) {
    const root = query('GET_ROOT');

    return Boolean(root?.element?.closest('[data-cook-description-image-upload]'));
}

function fileFromAddItem(item) {
    if (item?.file instanceof File) {
        return item.file;
    }

    if (item?.source instanceof File) {
        return item.source;
    }

    return null;
}

function applyOptimizedFileToAddItem(item, optimized) {
    item.file = optimized;
    item.source = optimized;
}

function installFilePondAddItemFilter() {
    const { FilePond } = window;

    if (! FilePond?.addFilter || FilePond.__cookDescriptionAddItemFilter) {
        return Boolean(FilePond?.__cookDescriptionAddItemFilter);
    }

    FilePond.addFilter('ADD_ITEM', (item, { query }) => {
        if (! isCookDescriptionPond(query)) {
            return item;
        }

        const file = fileFromAddItem(item);

        if (! file || ! file.type.startsWith('image/')) {
            return item;
        }

        return optimizeCookDescriptionImage(file)
            .then((optimized) => {
                if (optimized !== file) {
                    applyOptimizedFileToAddItem(item, optimized);
                }

                return item;
            })
            .catch((error) => {
                showCookDescriptionImageError(error);

                return Promise.reject(error);
            });
    });

    FilePond.__cookDescriptionAddItemFilter = true;

    return true;
}

function installFilePondHooks() {
    return installFilePondAddItemFilter();
}

function stopHookPolling() {
    if (hookPollIntervalId === null) {
        return;
    }

    window.clearInterval(hookPollIntervalId);
    hookPollIntervalId = null;
}

function startHookPolling() {
    if (hookPollIntervalId !== null || installFilePondHooks()) {
        return;
    }

    let attempts = 0;

    hookPollIntervalId = window.setInterval(() => {
        if (installFilePondHooks() || ++attempts >= HOOK_POLL_MAX_ATTEMPTS) {
            stopHookPolling();
        }
    }, HOOK_POLL_MS);
}

function scheduleCookDescriptionUploadHooks() {
    if (! document.querySelector('[data-cook-description-image-upload]')) {
        return;
    }

    if (! installFilePondHooks()) {
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
