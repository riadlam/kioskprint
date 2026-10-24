async function pdfjs() {
    const pdfjsLib = await import('pdfjs-dist');

    // Run parsing on the page thread. Phones (especially iOS) often fail to
    // start pdf.js's module worker, which leaves the sheet blank.
    if (!globalThis.pdfjsWorker?.WorkerMessageHandler) {
        await import('pdfjs-dist/build/pdf.worker.min.mjs');
    }

    return pdfjsLib;
}

export function isPdfFile(file) {
    return file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
}

export function isImageFile(file) {
    return file.type.startsWith('image/') || /\.(jpe?g|png|webp)$/i.test(file.name);
}

export async function countPdfPages(file) {
    const pdfjsLib = await pdfjs();
    const data = await file.arrayBuffer();
    const pdf = await openDocument(pdfjsLib, data);
    const pages = pdf.numPages;
    await pdf.destroy();

    return pages;
}

function openDocument(pdfjsLib, data) {
    return pdfjsLib.getDocument({
        data,
        cMapUrl: '/pdfjs/cmaps/',
        cMapPacked: true,
        standardFontDataUrl: '/pdfjs/standard_fonts/',
        useSystemFonts: false,
        useWorkerFetch: false,
        isOffscreenCanvasSupported: false,
    }).promise;
}

const localPdfCache = new Map();

export async function loadLocalPdf(file) {
    if (!localPdfCache.has(file)) {
        const pending = file.arrayBuffer()
            .then(async (data) => {
                const pdfjsLib = await pdfjs();
                return openDocument(pdfjsLib, data.slice(0));
            })
            .catch((error) => {
                localPdfCache.delete(file);
                throw error;
            });
        localPdfCache.set(file, pending);
    }

    return localPdfCache.get(file);
}

const canvasJobs = new WeakMap();

function paintCanvas(canvas, job) {
    const previous = canvasJobs.get(canvas) ?? Promise.resolve();
    const current = previous.catch(() => {}).then(job);
    canvasJobs.set(canvas, current);

    return current;
}

function canvasContext(canvas) {
    return canvas.getContext('2d', { alpha: false }) || canvas.getContext('2d');
}

export async function renderPdfPageToCanvas(pdf, pageNumber, canvas, targetWidth = 240) {
    return paintCanvas(canvas, async () => {
        const page = await pdf.getPage(pageNumber);
        const unscaled = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: targetWidth / unscaled.width });
        const context = canvasContext(canvas);
        canvas.width = Math.ceil(viewport.width);
        canvas.height = Math.ceil(viewport.height);
        const task = page.render({ canvasContext: context, viewport });
        await task.promise;

        return task;
    });
}

/** ISO A4 ratio used for print preview sheets. */
export const A4_RATIO = 210 / 297;

/**
 * Render a PDF page into a canvas fitted inside an A4 frame (contain),
 * with a white paper background — like a real printable sheet.
 */
export async function renderPdfPageAsA4(pdf, pageNumber, canvas, frameWidth, frameHeight, dpr = window.devicePixelRatio || 1) {
    return paintCanvas(canvas, async () => {
        const page = await pdf.getPage(pageNumber);
        const unscaled = page.getViewport({ scale: 1 });
        const scale = Math.min(frameWidth / unscaled.width, frameHeight / unscaled.height);
        const viewport = page.getViewport({ scale: scale * dpr });

        canvas.width = Math.ceil(viewport.width);
        canvas.height = Math.ceil(viewport.height);
        canvas.style.width = `${viewport.width / dpr}px`;
        canvas.style.height = `${viewport.height / dpr}px`;

        const context = canvasContext(canvas);
        const task = page.render({ canvasContext: context, viewport });
        await task.promise;

        return task;
    });
}

const remotePdfCache = new Map();

export async function loadPdfDocument(url) {
    if (!remotePdfCache.has(url)) {
        const pending = (async () => {
            const pdfjsLib = await pdfjs();
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error(`Unexpected PDF response (${response.status}).`);
            }
            const data = new Uint8Array(await response.arrayBuffer());

            return openDocument(pdfjsLib, data);
        })().catch((error) => {
            remotePdfCache.delete(url);
            throw error;
        });
        remotePdfCache.set(url, pending);
    }

    return remotePdfCache.get(url);
}
