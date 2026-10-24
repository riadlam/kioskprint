export const ACCEPT = 'image/*,application/pdf,.pdf,.jpg,.jpeg,.png,.webp';
const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
const MAX_BYTES = 12 * 1024 * 1024;

export function isAndroid() {
    return /Android/i.test(navigator.userAgent || '');
}

export function isMobile() {
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
}

export function extensionOf(file) {
    return file.name.split('.').pop()?.toLowerCase() || '';
}

export function isSupported(file) {
    const ext = extensionOf(file);
    if (EXTENSIONS.includes(ext)) {
        return true;
    }

    if (!file.type) {
        return false;
    }

    return ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)
        || file.type.startsWith('image/');
}

export function isTooBig(file) {
    return file.size > MAX_BYTES;
}

export function formatSize(bytes) {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function makeItemId() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    return `f-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export { countPdfPages, isPdfFile, isImageFile } from './pdfPreview';

export function pickPrintFiles(inputEl) {
    if (inputEl) {
        return pickWithExistingInput(inputEl);
    }

    if (typeof window.showOpenFilePicker === 'function' && !isMobile()) {
        return pickWithFileSystemAccess();
    }

    return pickWithInput();
}

function pickWithExistingInput(input) {
    return new Promise((resolve) => {
        const onChange = () => {
            cleanup();
            resolve({
                files: Array.from(input.files || []),
                permission: input.files?.length ? 'granted' : 'dismissed',
            });
            input.value = '';
        };

        const onCancel = () => {
            cleanup();
            resolve({ files: [], permission: 'dismissed' });
        };

        const cleanup = () => {
            input.removeEventListener('change', onChange);
            input.removeEventListener('cancel', onCancel);
        };

        input.addEventListener('change', onChange, { once: true });
        input.addEventListener('cancel', onCancel, { once: true });
        input.click();
    });
}

async function pickWithFileSystemAccess() {
    try {
        const handles = await window.showOpenFilePicker({
            multiple: true,
            types: [
                {
                    description: 'PDF and images',
                    accept: {
                        'application/pdf': ['.pdf'],
                        'image/*': ['.jpg', '.jpeg', '.png', '.webp'],
                    },
                },
            ],
        });
        const files = await Promise.all(handles.map((handle) => handle.getFile()));

        return { files, permission: files.length ? 'granted' : 'dismissed' };
    } catch (error) {
        if (error?.name === 'AbortError') {
            return { files: [], permission: 'dismissed' };
        }

        if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
            return { files: [], permission: 'denied' };
        }

        return pickWithInput();
    }
}

function pickWithInput() {
    return new Promise((resolve) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = ACCEPT;
        input.style.display = 'none';
        let settled = false;

        const finish = (result) => {
            if (settled) {
                return;
            }
            settled = true;
            input.remove();
            resolve(result);
        };

        input.addEventListener('change', () => {
            finish({
                files: Array.from(input.files || []),
                permission: input.files?.length ? 'granted' : 'dismissed',
            });
        });

        input.addEventListener('cancel', () => {
            finish({ files: [], permission: 'dismissed' });
        });

        document.body.appendChild(input);
        input.click();
    });
}
