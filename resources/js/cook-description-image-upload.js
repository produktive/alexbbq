import {
    isCookDescriptionImageInput,
    optimizeCookDescriptionImage,
} from './cook-description-image';

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

    if (! FilePond?.create || FilePond.__cookDescriptionHookInstalled) {
        return;
    }

    const originalCreate = FilePond.create.bind(FilePond);

    FilePond.create = (input, options = {}) => {
        if (isCookDescriptionImageInput(input)) {
            options = {
                ...options,
                beforeAddFile: chainBeforeAddFile(
                    options.beforeAddFile,
                    optimizeCookDescriptionFileItem,
                ),
            };
        }

        return originalCreate(input, options);
    };

    FilePond.__cookDescriptionHookInstalled = true;
}

function watchForFilePond() {
    installFilePondCookDescriptionHook();

    if (window.FilePond?.__cookDescriptionHookInstalled) {
        return;
    }

    let filePond = window.FilePond;

    Object.defineProperty(window, 'FilePond', {
        configurable: true,
        enumerable: true,
        get() {
            return filePond;
        },
        set(value) {
            filePond = value;
            installFilePondCookDescriptionHook();
        },
    });
}

watchForFilePond();
