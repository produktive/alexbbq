import {
    cookDescriptionImageFileSignature,
    isImageUploadFile,
    optimizeCookDescriptionImage,
} from './cook-description-image';

const COOK_DESCRIPTION_UPLOAD_SELECTOR = '[data-cook-description-image-upload], .cook-description-image-upload';
const HOOK_POLL_MS = 16;
const HOOK_POLL_MAX_ATTEMPTS = 1200;

const preparedFiles = new WeakSet();
const preparedFileSignatures = new Set();

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

function findCookDescriptionUploadScopes() {
    return document.querySelectorAll(COOK_DESCRIPTION_UPLOAD_SELECTOR);
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

function markPreparedFile(file) {
    preparedFiles.add(file);
    preparedFileSignatures.add(cookDescriptionImageFileSignature(file));
}

function isPreparedFile(file) {
    return preparedFiles.has(file)
        || preparedFileSignatures.has(cookDescriptionImageFileSignature(file));
}

function createCookDescriptionImageOptimizerPlugin() {
    return ({ addFilter, utils }) => {
        const { Type } = utils;

        addFilter('LOAD_FILE', (source, { query }) => {
            if (! query('GET_COOK_DESCRIPTION_IMAGE_UPLOAD')) {
                return Promise.resolve(source);
            }

            if (! (source instanceof File) || isPreparedFile(source) || ! isImageUploadFile(source)) {
                return Promise.resolve(source);
            }

            return optimizeCookDescriptionImage(source)
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
