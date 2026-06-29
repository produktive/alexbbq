export const COOK_DESCRIPTION_IMAGE_MAX_BYTES = 2 * 1024 * 1024;
export const COOK_DESCRIPTION_IMAGE_MAX_WIDTH = 1920;
export const COOK_DESCRIPTION_IMAGE_MAX_HEIGHT = 1920;

function swapExtension(filename, extension) {
    const base = filename.replace(/\.[^.]+$/, '') || 'image';

    return `${base}.${extension}`;
}

function scaleDimensions(width, height, maxWidth, maxHeight) {
    const scale = Math.min(1, maxWidth / width, maxHeight / height);

    return {
        width: Math.max(1, Math.round(width * scale)),
        height: Math.max(1, Math.round(height * scale)),
    };
}

function canvasToBlob(canvas, type, quality) {
    return new Promise((resolve) => {
        canvas.toBlob(resolve, type, quality);
    });
}

async function loadImageSource(file) {
    if (typeof createImageBitmap === 'function') {
        const bitmap = await createImageBitmap(file);

        return {
            draw(ctx, width, height) {
                ctx.drawImage(bitmap, 0, 0, width, height);
            },
            width: bitmap.width,
            height: bitmap.height,
            close() {
                bitmap.close?.();
            },
        };
    }

    const url = URL.createObjectURL(file);

    try {
        const image = await new Promise((resolve, reject) => {
            const element = new Image();
            element.onload = () => resolve(element);
            element.onerror = () => reject(new Error('Could not read this image.'));
            element.src = url;
        });

        return {
            draw(ctx, width, height) {
                ctx.drawImage(image, 0, 0, width, height);
            },
            width: image.naturalWidth,
            height: image.naturalHeight,
            close() {},
        };
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function encodeUnderBudget(canvas) {
    const supportsWebp = canvas.toDataURL('image/webp').startsWith('data:image/webp');

    if (supportsWebp) {
        for (let quality = 0.85; quality >= 0.45; quality -= 0.05) {
            const blob = await canvasToBlob(canvas, 'image/webp', quality);

            if (blob && blob.size <= COOK_DESCRIPTION_IMAGE_MAX_BYTES) {
                return { blob, extension: 'webp', type: 'image/webp' };
            }
        }
    }

    for (let quality = 0.85; quality >= 0.45; quality -= 0.05) {
        const blob = await canvasToBlob(canvas, 'image/jpeg', quality);

        if (blob && blob.size <= COOK_DESCRIPTION_IMAGE_MAX_BYTES) {
            return { blob, extension: 'jpg', type: 'image/jpeg' };
        }
    }

    return null;
}

export async function optimizeCookDescriptionImage(file) {
    if (! file.type.startsWith('image/')) {
        return file;
    }

    if (file.type === 'image/gif') {
        if (file.size <= COOK_DESCRIPTION_IMAGE_MAX_BYTES) {
            return file;
        }

        throw new Error('GIF images must be 2 MB or smaller.');
    }

    if (file.size <= COOK_DESCRIPTION_IMAGE_MAX_BYTES && file.type === 'image/webp') {
        const source = await loadImageSource(file);

        try {
            if (source.width <= COOK_DESCRIPTION_IMAGE_MAX_WIDTH && source.height <= COOK_DESCRIPTION_IMAGE_MAX_HEIGHT) {
                return file;
            }
        } finally {
            source.close();
        }
    }

    const source = await loadImageSource(file);

    try {
        const { width, height } = scaleDimensions(
            source.width,
            source.height,
            COOK_DESCRIPTION_IMAGE_MAX_WIDTH,
            COOK_DESCRIPTION_IMAGE_MAX_HEIGHT,
        );

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');

        if (! context) {
            throw new Error('Could not process this image.');
        }

        if (file.type === 'image/png') {
            context.clearRect(0, 0, width, height);
        }

        source.draw(context, width, height);

        const encoded = await encodeUnderBudget(canvas);

        if (! encoded) {
            throw new Error('This image could not be reduced below 2 MB. Try a smaller photo.');
        }

        return new File(
            [encoded.blob],
            swapExtension(file.name, encoded.extension),
            { type: encoded.type, lastModified: Date.now() },
        );
    } finally {
        source.close();
    }
}

export function isCookDescriptionImageInput(input) {
    return input instanceof HTMLInputElement
        && input.type === 'file'
        && Boolean(input.closest('[data-cook-description-image-upload]'));
}
