const COOK_DESCRIPTION_IMAGE_MAX_BYTES = 2 * 1024 * 1024;
const COOK_DESCRIPTION_IMAGE_MAX_WIDTH = 1920;
const COOK_DESCRIPTION_IMAGE_MAX_HEIGHT = 1920;

export const COOK_DESCRIPTION_UPLOAD_SELECTOR = '[data-cook-description-image-upload]';

const IMAGE_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif']);

let wasmWebpEncoderPromise = null;
let canvasWebpEncodeSupported = null;

export function isImageUploadFile(file) {
    if (! (file instanceof File)) {
        return false;
    }

    if (file.type.startsWith('image/')) {
        return true;
    }

    if (file.type !== '' && file.type !== 'application/octet-stream') {
        return false;
    }

    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    return IMAGE_EXTENSIONS.has(extension);
}

export function cookDescriptionImageFileSignature(file) {
    return `${file.name}:${file.size}:${file.type}:${file.lastModified}`;
}

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

function supportsCanvasWebpEncode() {
    if (canvasWebpEncodeSupported !== null) {
        return canvasWebpEncodeSupported;
    }

    try {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;

        canvasWebpEncodeSupported = canvas.toDataURL('image/webp').startsWith('data:image/webp');
    } catch {
        canvasWebpEncodeSupported = false;
    }

    return canvasWebpEncodeSupported;
}

function loadWasmWebpEncoder() {
    if (! wasmWebpEncoderPromise) {
        wasmWebpEncoderPromise = import('@jsquash/webp').then((module) => module.encode);
    }

    return wasmWebpEncoderPromise;
}

async function loadImageSource(file) {
    const preferImageElement = typeof createImageBitmap !== 'function'
        || (navigator.maxTouchPoints > 0 && file.type === 'image/jpeg');

    if (! preferImageElement && typeof createImageBitmap === 'function') {
        try {
            const bitmap = await createImageBitmap(file, {
                imageOrientation: 'from-image',
            });

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
        } catch {
            // Fall back to Image() below.
        }
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

async function encodeWebpViaCanvas(canvas) {
    for (let quality = 0.85; quality >= 0.45; quality -= 0.05) {
        const blob = await canvasToBlob(canvas, 'image/webp', quality);

        if (blob instanceof Blob && blob.size > 0 && blob.size <= COOK_DESCRIPTION_IMAGE_MAX_BYTES) {
            return { blob, extension: 'webp', type: 'image/webp' };
        }
    }

    return null;
}

async function encodeWebpViaWasm(canvas) {
    const context = canvas.getContext('2d');

    if (! context) {
        return null;
    }

    let encode;

    try {
        encode = await loadWasmWebpEncoder();
    } catch {
        return null;
    }

    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);

    for (let quality = 85; quality >= 45; quality -= 5) {
        try {
            const webpBuffer = await encode(imageData, { quality });
            const blob = new Blob([webpBuffer], { type: 'image/webp' });

            if (blob.size <= COOK_DESCRIPTION_IMAGE_MAX_BYTES) {
                return { blob, extension: 'webp', type: 'image/webp' };
            }
        } catch {
            continue;
        }
    }

    return null;
}

async function encodeJpegUnderBudget(canvas) {
    for (let quality = 0.85; quality >= 0.45; quality -= 0.05) {
        const blob = await canvasToBlob(canvas, 'image/jpeg', quality);

        if (blob && blob.size <= COOK_DESCRIPTION_IMAGE_MAX_BYTES) {
            return { blob, extension: 'jpg', type: 'image/jpeg' };
        }
    }

    return null;
}

async function encodeUnderBudget(canvas) {
    if (supportsCanvasWebpEncode()) {
        const canvasWebp = await encodeWebpViaCanvas(canvas);

        if (canvasWebp) {
            return canvasWebp;
        }
    }

    const wasmWebp = await encodeWebpViaWasm(canvas);

    if (wasmWebp) {
        return wasmWebp;
    }

    return encodeJpegUnderBudget(canvas);
}

export async function optimizeCookDescriptionImage(file) {
    if (! isImageUploadFile(file)) {
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
        && Boolean(input.closest(COOK_DESCRIPTION_UPLOAD_SELECTOR));
}
