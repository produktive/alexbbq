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
const pendingCookDescriptionFileSignatures = new Set();

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

function isTouchDevice() {
    return window.matchMedia('(pointer: coarse)').matches
        || navigator.maxTouchPoints > 0;
}

function findCookDescriptionUploadScopes() {
    return document.querySelectorAll(COOK_DESCRIPTION_UPLOAD_SELECTOR);
}

function isCookDescriptionUploadActive() {
    return [...findCookDescriptionUploadScopes()].some((scope) => {
        return scope.querySelector('.filepond--root') !== null
            || scope.querySelector('input[type="file"]') !== null;
    });
}

function getFileInputForScope(scope) {
    const fileUpload = scope.matches('.fi-fo-file-upload')
        ? scope
        : scope.querySelector('.fi-fo-file-upload');

    return (fileUpload ?? scope).querySelector('input[type="file"]');
}

function getPondForScope(scope) {
    const fileUpload = scope.matches('.fi-fo-file-upload')
        ? scope
        : scope.querySelector('.fi-fo-file-upload');

    const alpinePond = fileUpload?._x_dataStack?.[0]?.pond;

    if (alpinePond) {
        return alpinePond;
    }

    const input = getFileInputForScope(scope);
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

function markPendingCookDescriptionFile(file) {
    pendingCookDescriptionFileSignatures.add(cookDescriptionImageFileSignature(file));

    window.setTimeout(() => {
        pendingCookDescriptionFileSignatures.delete(cookDescriptionImageFileSignature(file));
    }, 30_000);
}

function isPendingCookDescriptionFile(file) {
    return pendingCookDescriptionFileSignatures.has(cookDescriptionImageFileSignature(file));
}

function toUploadFile(source) {
    if (source instanceof File) {
        return source;
    }

    if (source instanceof Blob) {
        const name = typeof source.name === 'string' && source.name !== ''
            ? source.name
            : 'image.jpg';

        return new File([source], name, {
            type: source.type || 'application/octet-stream',
            lastModified: Date.now(),
        });
    }

    return null;
}

function shouldOptimizeCookDescriptionSource(source, query) {
    if (query?.('GET_COOK_DESCRIPTION_IMAGE_UPLOAD')) {
        return true;
    }

    const file = toUploadFile(source);

    if (! file) {
        return false;
    }

    return isPendingCookDescriptionFile(file) || isCookDescriptionUploadActive();
}

function optimizeUploadSource(source) {
    const file = toUploadFile(source);

    if (! file || isPreparedFile(file) || ! isImageUploadFile(file)) {
        return Promise.resolve(source);
    }

    return optimizeCookDescriptionImage(file)
        .then((optimized) => {
            markPreparedFile(optimized);

            return optimized;
        })
        .catch((error) => {
            showCookDescriptionImageError(error);

            return Promise.reject({
                status: {
                    main: 'Image processing failed',
                    sub: error instanceof Error ? error.message : 'This image could not be processed.',
                },
            });
        });
}

function createCookDescriptionImageOptimizerPlugin() {
    return ({ addFilter, utils }) => {
        const { Type } = utils;

        addFilter('LOAD_FILE', (source, { query }) => {
            if (! shouldOptimizeCookDescriptionSource(source, query)) {
                return Promise.resolve(source);
            }

            return optimizeUploadSource(source);
        });

        return {
            options: {
                cookDescriptionImageUpload: [false, Type.BOOLEAN],
            },
        };
    };
}

function registerCookDescriptionImagePlugin() {
    if (window.__cookDescriptionFilePondPluginRegistered) {
        return true;
    }

    const { FilePond } = window;

    if (! FilePond?.registerPlugin) {
        return false;
    }

    FilePond.registerPlugin(createCookDescriptionImageOptimizerPlugin());
    window.__cookDescriptionFilePondPluginRegistered = true;

    return true;
}

async function optimizeLivewireUploadFormData(formData) {
    const entries = [...formData.entries()];
    const optimizedFormData = new FormData();

    for (const [key, value] of entries) {
        if (key === 'files[]' && value instanceof File && ! isPreparedFile(value) && isImageUploadFile(value)) {
            const optimized = await optimizeCookDescriptionImage(value);

            markPreparedFile(optimized);
            optimizedFormData.append(key, optimized, optimized.name);

            continue;
        }

        optimizedFormData.append(key, value);
    }

    return optimizedFormData;
}

async function optimizeLivewireUploadBody(body) {
    if (body instanceof FormData) {
        return optimizeLivewireUploadFormData(body);
    }

    if (body instanceof File && ! isPreparedFile(body) && isImageUploadFile(body)) {
        const optimized = await optimizeCookDescriptionImage(body);

        markPreparedFile(optimized);

        return optimized;
    }

    return body;
}

function installLivewireUploadInterceptor() {
    if (window.__cookDescriptionLivewireUploadPatch) {
        return;
    }

    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.send = function send(body) {
        if (! isCookDescriptionUploadActive()) {
            return originalSend.call(this, body);
        }

        const shouldOptimizeFormData = body instanceof FormData
            && [...body.entries()].some(([key, value]) => {
                return key === 'files[]'
                    && value instanceof File
                    && ! isPreparedFile(value)
                    && isImageUploadFile(value);
            });

        const shouldOptimizeFile = body instanceof File
            && ! isPreparedFile(body)
            && isImageUploadFile(body);

        if (! shouldOptimizeFormData && ! shouldOptimizeFile) {
            return originalSend.call(this, body);
        }

        void optimizeLivewireUploadBody(body)
            .then((optimizedBody) => {
                originalSend.call(this, optimizedBody);
            })
            .catch((error) => {
                showCookDescriptionImageError(error);
            });

        return undefined;
    };

    window.__cookDescriptionLivewireUploadPatch = true;
}

function trackCookDescriptionFileSelection(event) {
    const input = event.target;

    if (! isCookDescriptionImageInput(input) || ! input.files?.length) {
        return;
    }

    markPendingCookDescriptionFile(input.files[0]);
}

function handleMobileBrowseFileSelection(event) {
    if (! isTouchDevice()) {
        return;
    }

    const input = event.target;

    if (! isCookDescriptionImageInput(input) || ! input.files?.length) {
        return;
    }

    if (input.dataset.cookDescriptionSkipOptimize === '1') {
        delete input.dataset.cookDescriptionSkipOptimize;

        return;
    }

    const selectedFile = input.files[0];

    if (isPreparedFile(selectedFile) || input.dataset.cookDescriptionOptimizing === '1') {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    input.dataset.cookDescriptionOptimizing = '1';

    const scope = input.closest(COOK_DESCRIPTION_UPLOAD_SELECTOR);

    markPendingCookDescriptionFile(selectedFile);

    void optimizeCookDescriptionImage(selectedFile)
        .then(async (optimized) => {
            markPreparedFile(optimized);

            const pond = getPondForScope(scope) ?? await waitForPond(scope);

            if (! pond) {
                throw new Error('Could not prepare this image for upload.');
            }

            input.value = '';
            await pond.addFile(optimized, {
                metadata: { cookDescriptionOptimized: true },
            });
        })
        .catch((error) => {
            input.value = '';
            showCookDescriptionImageError(error);
        })
        .finally(() => {
            delete input.dataset.cookDescriptionOptimizing;
        });
}

function installFileSelectionTracker() {
    if (window.__cookDescriptionFileSelectionTracker) {
        return;
    }

    document.addEventListener('change', trackCookDescriptionFileSelection, true);
    document.addEventListener('input', trackCookDescriptionFileSelection, true);
    document.addEventListener('change', handleMobileBrowseFileSelection, true);
    document.addEventListener('input', handleMobileBrowseFileSelection, true);

    window.__cookDescriptionFileSelectionTracker = true;
}

function configureCookDescriptionPond(scope) {
    const pond = getPondForScope(scope);

    if (! pond) {
        return false;
    }

    if (! pond.__cookDescriptionImageConfigured) {
        pond.setOptions({ cookDescriptionImageUpload: true });
        pond.__cookDescriptionImageConfigured = true;
    }

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
        registerCookDescriptionImagePlugin();

        const scopes = findCookDescriptionUploadScopes();
        let allConfigured = scopes.length > 0;

        scopes.forEach((scope) => {
            if (! configureCookDescriptionPond(scope)) {
                allConfigured = false;
            }
        });

        if (allConfigured || ++attempts >= HOOK_POLL_MAX_ATTEMPTS) {
            stopHookPolling();
        }
    }, HOOK_POLL_MS);
}

function scheduleCookDescriptionUploadHooks() {
    installFileSelectionTracker();
    installLivewireUploadInterceptor();

    const scopes = findCookDescriptionUploadScopes();

    if (! scopes.length) {
        return;
    }

    if (! registerCookDescriptionImagePlugin()) {
        startHookPolling();

        return;
    }

    let allConfigured = true;

    scopes.forEach((scope) => {
        if (! configureCookDescriptionPond(scope)) {
            allConfigured = false;
        }
    });

    if (! allConfigured) {
        startHookPolling();
    }
}

function initCookDescriptionImageUploadHooks() {
    installFileSelectionTracker();
    installLivewireUploadInterceptor();

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
