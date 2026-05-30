const MAX_INPUT_BYTES = 10 * 1024 * 1024; // 10 MB hard cap
const TARGET_BYTES    = 1 * 1024 * 1024;  // 1 MB target after compression
const DEFAULT_QUALITY = 0.85;
const MIN_QUALITY     = 0.4;

export const MAX_INPUT_MB = MAX_INPUT_BYTES / (1024 * 1024);

export class ImageTooLargeError extends Error {
    constructor() {
        super('Image too large');
        this.code = 'IMAGE_TOO_LARGE';
    }
}

export class ImageCompressError extends Error {
    constructor() {
        super('Image compression failed');
        this.code = 'IMAGE_COMPRESS_FAILED';
    }
}

function loadImage(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload  = () => resolve(img);
            img.onerror = () => reject(new ImageCompressError());
            img.src     = e.target.result;
        };
        reader.onerror = () => reject(new ImageCompressError());
        reader.readAsDataURL(file);
    });
}

function renderToBlob(img, { minSide, quality, mimeType }) {
    // Cap the SHORTER side at minSide. The longer side scales proportionally.
    // Verticals stay tall, horizontals stay wide, nothing gets cropped, and
    // neither dimension becomes uselessly small.
    let { naturalWidth: width, naturalHeight: height } = img;
    const shorter = Math.min(width, height);
    if (shorter > minSide) {
        const ratio = minSide / shorter;
        width  = Math.round(width  * ratio);
        height = Math.round(height * ratio);
    }

    const canvas = document.createElement('canvas');
    canvas.width  = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(img, 0, 0, width, height);

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => blob ? resolve(blob) : reject(new ImageCompressError()),
            mimeType,
            quality,
        );
    });
}

/**
 * Compress an image client-side:
 *  - Hard-reject anything over MAX_INPUT_BYTES (ImageTooLargeError).
 *  - Cap the shorter side at `minSide` (aspect ratio preserved, nothing cropped).
 *  - Convert to WebP, lower quality iteratively until under TARGET_BYTES.
 *
 * @returns {Promise<File>} compressed WebP file ready to upload.
 */
export async function compressImage(file, { minSide = 512 } = {}) {
    if (!file) throw new ImageCompressError();
    if (file.size > MAX_INPUT_BYTES) throw new ImageTooLargeError();

    const img = await loadImage(file);

    let quality = DEFAULT_QUALITY;
    let blob    = await renderToBlob(img, { minSide, quality, mimeType: 'image/webp' });

    while (blob.size > TARGET_BYTES && quality > MIN_QUALITY) {
        quality = Math.round((quality - 0.1) * 100) / 100;
        blob    = await renderToBlob(img, { minSide, quality, mimeType: 'image/webp' });
    }

    const baseName = file.name.replace(/\.[^.]+$/, '') || 'image';
    return new File([blob], `${baseName}.webp`, { type: 'image/webp' });
}
