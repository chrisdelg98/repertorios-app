import { ref } from 'vue';

/**
 * Uploads a rehearsal track straight from the browser to R2.
 *
 * Three steps: ask this server to sign an upload URL, PUT the file to that URL,
 * then tell the server the upload landed. The file never passes through PHP,
 * which is what keeps it clear of upload_max_filesize and of the hosting's
 * bandwidth.
 *
 * XMLHttpRequest rather than fetch, only because fetch still cannot report
 * upload progress — and a 20 MB upload with no progress bar feels broken.
 */

/** Kept in step with SongAudioController: MP3 only, 5 MB. */
const ACCEPTED = ['audio/mpeg'];
export const MAX_BYTES = 5 * 1024 * 1024;

/** Some browsers report an empty or odd type for mp3; the extension settles it. */
function resolveMime(file) {
    if (ACCEPTED.includes(file.type)) return 'audio/mpeg';

    return file.name.split('.').pop()?.toLowerCase() === 'mp3' ? 'audio/mpeg' : null;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function json(url, body, method = 'POST') {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(payload.message ?? `${method} ${url} → ${response.status}`);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

function putToStorage(url, file, onProgress) {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();

        request.open('PUT', url, true);
        // Must match the type that was signed, or R2 rejects the signature.
        request.setRequestHeader('Content-Type', file.type || 'application/octet-stream');

        request.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) onProgress(Math.round((event.loaded / event.total) * 100));
        });

        request.addEventListener('load', () => {
            request.status >= 200 && request.status < 300
                ? resolve()
                : reject(new Error(`storage_rejected_${request.status}`));
        });

        request.addEventListener('error', () => reject(new Error('storage_unreachable')));
        request.addEventListener('abort', () => reject(new Error('cancelled')));

        request.send(file);
    });
}

export function useAudioUpload() {
    const uploading = ref(false);
    const progress = ref(0);
    const error = ref('');

    async function upload(songVersionId, file) {
        error.value = '';
        progress.value = 0;

        const mime = resolveMime(file);

        if (!mime) {
            error.value = 'unsupported_type';
            return null;
        }

        if (file.size > MAX_BYTES) {
            error.value = 'too_large';
            return null;
        }

        uploading.value = true;

        try {
            const signed = await json(`/song-versions/${songVersionId}/audio/presign`, {
                mime,
                size: file.size,
            });

            // The signed Content-Type has to match byte for byte.
            const typed = file.type === mime ? file : new File([file], file.name, { type: mime });

            await putToStorage(signed.url, typed, (value) => { progress.value = value; });

            const result = await json(`/song-versions/${songVersionId}/audio`, {
                key: signed.key,
                name: file.name,
                size: file.size,
                mime,
            }, 'PUT');

            return result.audio;
        } catch (e) {
            console.error('[audio] upload failed', e);

            error.value = e.status === 422 && e.payload?.errors?.size
                ? 'too_large'
                : (e.message ?? 'failed');

            return null;
        } finally {
            uploading.value = false;
        }
    }

    async function remove(songVersionId) {
        error.value = '';

        try {
            await json(`/song-versions/${songVersionId}/audio`, null, 'DELETE');
            return true;
        } catch (e) {
            console.error('[audio] delete failed', e);
            error.value = e.message ?? 'failed';
            return false;
        }
    }

    return { uploading, progress, error, upload, remove, ACCEPTED };
}
