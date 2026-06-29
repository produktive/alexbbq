import {
    isCookDescriptionImageInput,
    optimizeCookDescriptionImage,
} from './cook-description-image';

const FILE_POND_POLL_MS = 100;
const FILE_POND_POLL_TIMEOUT_MS = 30_000;

function showCookDescriptionImageError(error) {
    const message = error instanceof Error
        ? error.message
        : 'This image could not be processed.';

    window.dispatchEvent(new CustomEvent('cook-description-image-error', {
        detail: { message },
    }));

    window.alert(message);
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

function installFilePondCookDescriptionHook() {
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
        }

        return originalCreate(input, options);
    }

    wrappedCreate.__cookDescriptionHook = true;
    FilePond.create = wrappedCreate;

    return true;
}

function watchForFilePond() {
    if (installFilePondCookDescriptionHook()) {
        return;
    }

    const startedAt = Date.now();

    const intervalId = window.setInterval(() => {
        if (installFilePondCookDescriptionHook()) {
            window.clearInterval(intervalId);

            return;
        }

        if (Date.now() - startedAt >= FILE_POND_POLL_TIMEOUT_MS) {
            window.clearInterval(intervalId);
        }
    }, FILE_POND_POLL_MS);
}

watchForFilePond();

document.addEventListener('livewire:navigated', watchForFilePond);
