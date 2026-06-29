import { isCookDescriptionImageInput, optimizeCookDescriptionImage } from './cook-description-image';

const COOK_DESCRIPTION_UPLOAD_SELECTOR = '[data-cook-description-image-upload], .cook-description-image-upload';
const HOOK_POLL_MS = 16;
const HOOK_POLL_MAX_ATTEMPTS = 1200;

const preparedFiles = new WeakSet();
const hookedPonds = new WeakSet();

let hookPollIntervalId = null;
let documentDropHandlerInstalled = false;

function showCookDescriptionImageError(error) {
    const message = error instanceof Error
        ? error.message
        : 'This image could not be processed.';

    window.dispatchEvent(new CustomEvent('cook-description-image-error', {
        detail: { message },
    }));

    window.alert(message);
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

function optimizeForUpload(file) {
    if (! (file instanceof File) || ! file.type.startsWith('image/')) {
        return Promise.resolve(file);
    }

    if (preparedFiles.has(file)) {
        return Promise.resolve(file);
    }

    return optimizeCookDescriptionImage(file)
        .then((optimized) => {
            preparedFiles.add(optimized);

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

    if (! (file instanceof File) || ! file.type.startsWith('image/')) {
        return true;
    }

    if (preparedFiles.has(file)) {
        fileItem.setMetadata('cookDescriptionOptimized', true);

        return true;
    }

    return false;
}

async function replaceFileItemWithOptimized(pond, fileItem, file) {
    fileItem.setMetadata('cookDescriptionOptimizing', true);

    const itemId = fileItem.id;

    try {
        await fileItem.abortProcessing().catch(() => {});

        const optimized = await optimizeForUpload(file);
        const item = pond.getFiles().find((candidate) => candidate.id === itemId);

        if (! item) {
            await pond.addFile(optimized, {
                metadata: { cookDescriptionOptimized: true },
            });

            return;
        }

        item.setFile(optimized);
        item.setMetadata('cookDescriptionOptimized', true);
        item.setMetadata('cookDescriptionOptimizing', false);
        await pond.processFile(item.id);
    } catch {
        fileItem.setMetadata('cookDescriptionOptimizing', false);

        try {
            await pond.removeFile(itemId, { revert: false });
        } catch {
            // Ignore cleanup failures.
        }
    }
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

        if (fileItem.getMetadata('cookDescriptionOptimizing')) {
            return;
        }

        void replaceFileItemWithOptimized(pond, fileItem, fileItem.file);
    });

    pond.on('processfilestart', (fileItem) => {
        if (fileItem.getMetadata('cookDescriptionOptimizing')) {
            void fileItem.abortProcessing().catch(() => {});

            return;
        }

        if (shouldSkipFileItem(fileItem)) {
            return;
        }

        void replaceFileItemWithOptimized(pond, fileItem, fileItem.file);
    });
}

async function addOptimizedFileToPond(pond, file) {
    const optimized = await optimizeForUpload(file);

    await pond.addFile(optimized, {
        metadata: { cookDescriptionOptimized: true },
    });
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

        if (! (file instanceof File) || ! file.type.startsWith('image/')) {
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
                await addOptimizedFileToPond(pond, file);
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

function installChangeHandler() {
    if (window.__cookDescriptionImageChangeHandler) {
        return;
    }

    document.addEventListener('change', async (event) => {
        const input = event.target;

        if (! isCookDescriptionImageInput(input) || ! input.files?.length) {
            return;
        }

        if (input.dataset.cookDescriptionOptimizing === '1') {
            return;
        }

        event.stopImmediatePropagation();

        input.dataset.cookDescriptionOptimizing = '1';

        try {
            const optimized = await optimizeForUpload(input.files[0]);
            const transfer = new DataTransfer();

            transfer.items.add(optimized);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        } catch {
            input.value = '';
        } finally {
            delete input.dataset.cookDescriptionOptimizing;
        }
    }, true);

    window.__cookDescriptionImageChangeHandler = true;
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
    installChangeHandler();
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
