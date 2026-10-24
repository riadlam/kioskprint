const memory = new Map();
const DB_NAME = 'metbati-print';
const STORE = 'drafts';

function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            request.result.createObjectStore(STORE);
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function remember(jobId, draft) {
    memory.set(String(jobId), draft);

    return draft;
}

export function mimeOf(file) {
    if (file.type) {
        return file.type;
    }
    if (/\.pdf$/i.test(file.name)) {
        return 'application/pdf';
    }
    if (/\.png$/i.test(file.name)) {
        return 'image/png';
    }
    if (/\.webp$/i.test(file.name)) {
        return 'image/webp';
    }

    return 'image/jpeg';
}

function blobUrl(file) {
    if (!file.url) {
        file.url = URL.createObjectURL(file.file);
    }

    return file.url;
}

export function pagesFromDraft(draft) {
    const files = new Map(draft.files.map((file) => [file.key, file]));

    return draft.pages.map((page, index) => {
        const file = files.get(page.fileKey);

        return {
            id: page.id,
            number: page.number,
            index: index + 1,
            fileKey: page.fileKey,
            document: {
                id: page.fileKey,
                name: file.name,
                mime: file.type,
                page_count: file.pages,
                url: blobUrl(file),
            },
        };
    });
}

export async function readDraft(jobId) {
    const cached = memory.get(String(jobId));
    if (cached) {
        return cached;
    }

    try {
        const database = await openDb();
        const stored = await new Promise((resolve, reject) => {
            const tx = database.transaction(STORE, 'readonly');
            const request = tx.objectStore(STORE).get(String(jobId));
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });

        if (!stored) {
            return null;
        }

        return remember(jobId, {
            duplex: Boolean(stored.duplex),
            files: stored.files.map((file) => ({
                key: file.key,
                name: file.name,
                type: file.type,
                pages: file.pages,
                file: new File([file.blob], file.name, { type: file.type }),
            })),
            pages: stored.pages,
        });
    } catch {
        return null;
    }
}

export async function saveDraft(jobId, draft) {
    remember(jobId, draft);

    try {
        const database = await openDb();
        await new Promise((resolve, reject) => {
            const tx = database.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).put({
                duplex: draft.duplex,
                files: draft.files.map((file) => ({
                    key: file.key,
                    name: file.name,
                    type: file.type,
                    pages: file.pages,
                    blob: file.file,
                })),
                pages: draft.pages.map((page) => ({
                    id: page.id,
                    fileKey: page.fileKey,
                    number: page.number,
                })),
            }, String(jobId));
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    } catch {
        // The in-memory copy still covers this visit.
    }
}

export async function clearDraft(jobId) {
    memory.delete(String(jobId));

    try {
        const database = await openDb();
        await new Promise((resolve, reject) => {
            const tx = database.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).delete(String(jobId));
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    } catch {
        // Nothing left to upload from this tab.
    }
}
