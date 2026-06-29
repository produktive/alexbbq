import {
    cookDescriptionImageFileSignature,
    isCookDescriptionImageInput,
    isImageUploadFile,
    optimizeCookDescriptionImage,
} from './cook-description-image';

const COOK_DESCRIPTION_UPLOAD_SELECTOR = '[data-cook-description-image-upload], .cook-description-image-upload';
const HOOK_POLL_MS = 16;
const HOOK_POLL_MAX_ATTEMPTS = 1200;
const POND_WAIT_MS = 5000;

const preparedFiles = new WeakSet();
const preparedFileSignatures = new Set();
const hookedPonds = new WeakSet();

let hookPollIntervalId = null;
let documentDropHandlerInstalled = false;
let documentChangeHandlerInstalled = false;

function showCookDescriptionImageError(error) {
    const message = error instanceof Error
        ? error.message
        : 'This image could not be processed.';

    window.dispatchEvent(new CustomEvent('cook-description-image-error', {
        detail: { message },
    }));

    window.alert(message);
}

function prefersDirectPondUpload() {
    return window.matchMedia('(pointer: coarse)').matches
        || 'ontouchstart' in window
        || navigator.maxTouchPoints > 0;
}

function findCookDescriptionUploadScopes() {
    return document.querySelectorAll(COOK_DESCRIPTION_UPLOAD_SELECTOR);
}

function getScopeFromTarget(target) {
    if (! (target instanceof Element)) {
        return null;
    }

    return target.closest(COOK_DESCRIPTION_UPLOAD_SELECTOR);
}

function getPondForScope(scope) {
    const fileUpload = scope.matches('.fi-fo-file-upload')
        ? scope
        : scope.querySelector('.fi-fo-file-upload');

    const alpinePond = fileUpload?._x_dataStack?.[0]?.pond;

    if (alpinePond) {
        return alpinePond;
    }

    const input = (fileUpload ?? scope).querySelector('input[type="file"]');
    const { FilePond } = window;

    if (input && FilePond?.find) {
        return FilePond.find(input) ?? null;
    }

    return null;
}

async function waitForPond(scope) {
    const startedAt = Date.now();

    while (Date.now() - startedAt < POND_WAIT_MS) {
        const pond = getPondForScope(scope);

        if (pond) {
            return pond;
        }

        await new Promise((resolve) => {
            window.setTimeout(resolve, HOOK_POLL_MS);
        });
    }

    return null;
}

function markPreparedFile(file) {
    preparedFiles.add(file);
    preparedFileSignatures.add(cookDescriptionImageFileSignature(file));
}

function isPreparedFile(file) {
    return preparedFiles.has(file)
        || preparedFileSignatures.has(cookDescriptionImageFileSignature(file));
}

function optimizeForUpload(file) {
    if (! isImageUploadFile(file)) {
        return Promise.resolve(file);
    }

    if (isPreparedFile(file)) {
        return Promise.resolve(file);
    }

    return optimizeCookDescriptionImage(file)
        .then((optimized) => {
            markPreparedFile(optimized);

            return optimized;
        })
        .catch((error) => {
            showCookDescriptionImageError(error);

            return Promise.reject(error);
        });
}

function shouldSkipFileItem(fileItem) {
    if (! fileItem?.file) {
        return true;
    }

    if (fileItem.getMetadata('cookDescriptionOptimized')) {
        return true;
    }

    const file = fileItem.file;

    if (! isImageUploadFile(file)) {
        return true;
    }

    if (isPreparedFile(file)) {
        fileItem.setMetadata('cookDescriptionOptimized', true);

        return true;
    }

    return false;
}

function assignFileToInput(input, file) {
    try {
        const transfer = new DataTransfer();

        transfer.items.add(file);
        input.files = transfer.files;

        if (input.files.length !== 1) {
            return false;
        }

        const assigned = input.files[0];

        return assigned.name === file.name
            && assigned.size === file.size
            && assigned.type === file.type;
    } catch {
        return false;
    }
}

async function addOptimizedFileToPond(pond, file) {
    await pond.addFile(file, {
        metadata: { cookDescriptionOptimized: true },
    });
}

async function deliverOptimizedFileToPond(scope, input, file) {
    const pond = getPondForScope(scope) ?? await waitForPond(scope);

    if (prefersDirectPondUpload()) {
        if (! pond) {
            throw new Error('Could not prepare this image for upload.');
        }

        input.value = '';
        await addOptimizedFileToPond(pond, file);

        return;
    }

    if (assignFileToInput(input, file)) {
        input.dataset.cookDescriptionSkipOptimize = '1';
        input.dispatchEvent(new Event('change', { bubbles: true }));

        return;
    }

    if (! pond) {
        throw new Error('Could not prepare this image for upload.');
    }

    input.value = '';
    await addOptimizedFileToPond(pond, file);
}

function hookCookDescriptionPond(pond) {
    if (hookedPonds.has(pond)) {
        return;
    }

    hookedPonds.add(pond);

    pond.on('addfile', (fileItem) => {
        if (shouldSkipFileItem(fileItem)) {
            return;
        }

        const file = fileItem.file;

        fileItem.setMetadata('cookDescriptionOptimizing', true);

        void optimizeForUpload(file)
            .then(async (optimized) => {
                fileItem.setFile(optimized);
                fileItem.setMetadata('cookDescriptionOptimized', true);
                fileItem.setMetadata('cookDescriptionOptimizing', false);

                await pond.processFile(fileItem.id);
            })
            .catch(async () => {
                fileItem.setMetadata('cookDescriptionOptimizing', false);

                try {
                    await pond.removeFile(fileItem.id, { revert: false });
                } catch {
                    // Ignore cleanup failures.
                }
            });
    });

    pond.on('processfilestart', (fileItem) => {
        if (fileItem.getMetadata('cookDescriptionOptimizing')) {
            void fileItem.abortProcessing().catch(() => {});
        }
    });
}

async function addOptimizedFileToPondFromSource(pond, file) {
    const optimized = await optimizeForUpload(file);

    await addOptimizedFileToPond(pond, optimized);
}

function installDocumentChangeHandler() {
    if (documentChangeHandlerInstalled) {
        return;
    }

    documentChangeHandlerInstalled = true;

    document.addEventListener('change', (event) => {
        const input = event.target;

        if (! isCookDescriptionImageInput(input) || ! input.files?.length) {
            return;
        }

        if (input.dataset.cookDescriptionSkipOptimize === '1') {
            delete input.dataset.cookDescriptionSkipOptimize;

            return;
        }

        const selectedFile = input.files[0];

        if (isPreparedFile(selectedFile)) {
            return;
        }

        if (input.dataset.cookDescriptionOptimizing === '1') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        input.dataset.cookDescriptionOptimizing = '1';

        const scope = input.closest(COOK_DESCRIPTION_UPLOAD_SELECTOR);

        void optimizeForUpload(selectedFile)
            .then(async (optimized) => {
                await deliverOptimizedFileToPond(scope, input, optimized);
            })
            .catch(() => {
                input.value = '';
            })
            .finally(() => {
                delete input.dataset.cookDescriptionOptimizing;
            });
    }, true);
}

function installDocumentDropHandler() {
    if (documentDropHandlerInstalled) {
        return;
    }

    documentDropHandlerInstalled = true;

    document.addEventListener('dragover', (event) => {
        if (! getScopeFromTarget(event.target)) {
            return;
        }

        if (! event.dataTransfer?.types?.includes('Files')) {
            return;
        }

        event.preventDefault();
    }, true);

    document.addEventListener('drop', (event) => {
        const scope = getScopeFromTarget(event.target);

        if (! scope) {
            return;
        }

        const files = event.dataTransfer?.files;

        if (! files?.length) {
            return;
        }

        const file = files[0];

        if (! isImageUploadFile(file)) {
            return;
        }

        const pond = getPondForScope(scope);

        if (! pond) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        void (async () => {
            try {
                await addOptimizedFileToPondFromSource(pond, file);
            } catch {
                // Error already surfaced in optimizeForUpload().
            }
        })();
    }, true);
}

function hookCookDescriptionUploadScope(scope) {
    const pond = getPondForScope(scope);

    if (! pond) {
        return false;
    }

    hookCookDescriptionPond(pond);

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
    if (hookPollIntervalId !== null) {
        return;
    }

    let attempts = 0;

    hookPollIntervalId = window.setInterval(() => {
        const scopes = findCookDescriptionUploadScopes();
        let allHooked = scopes.length > 0;

        scopes.forEach((scope) => {
            if (! hookCookDescriptionUploadScope(scope)) {
                allHooked = false;
            }
        });

        if (allHooked || ++attempts >= HOOK_POLL_MAX_ATTEMPTS) {
            stopHookPolling();
        }
    }, HOOK_POLL_MS);
}

function scheduleCookDescriptionUploadHooks() {
    installDocumentChangeHandler();
    installDocumentDropHandler();

    const scopes = findCookDescriptionUploadScopes();

    if (! scopes.length) {
        return;
    }

    let allHooked = true;

    scopes.forEach((scope) => {
        if (! hookCookDescriptionUploadScope(scope)) {
            allHooked = false;
        }
    });

    if (! allHooked) {
        startHookPolling();
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

document.addEventListener('livewire:navigated', scheduleCookDescriptionUploadHooks);
document.addEventListener('FilePond:loaded', scheduleCookDescriptionUploadHooks);
