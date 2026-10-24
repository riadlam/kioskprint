import { useEffect, useRef, useState } from 'react';
import { isImageFile, isPdfFile, loadLocalPdf, renderPdfPageToCanvas } from '../../lib/pdfPreview';

export default function LocalAssetPreview({ file, mode = 'grid', pageNumber = 1 }) {
    const canvasRef = useRef(null);
    const [url, setUrl] = useState(null);
    const [failed, setFailed] = useState(false);
    const image = file ? isImageFile(file) : false;
    const pdf = file ? isPdfFile(file) : false;
    const wide = mode === 'hero';

    useEffect(() => {
        if (!file || !image) {
            setUrl(null);
            return undefined;
        }

        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);
        setFailed(false);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file, image]);

    useEffect(() => {
        if (!file || !pdf) {
            return undefined;
        }

        let cancelled = false;
        let task = null;

        (async () => {
            try {
                const doc = await loadLocalPdf(file);
                if (cancelled || !canvasRef.current) {
                    return;
                }
                task = await renderPdfPageToCanvas(doc, pageNumber, canvasRef.current, wide ? 560 : 220);
            } catch {
                if (!cancelled) {
                    setFailed(true);
                }
            }
        })();

        return () => {
            cancelled = true;
            task?.cancel?.();
        };
    }, [file, pdf, pageNumber, wide]);

    if (!file) {
        return null;
    }

    if (image && url) {
        return (
            <img
                src={url}
                alt=""
                className={wide ? 'h-full w-full object-contain' : 'h-full w-full object-cover'}
            />
        );
    }

    if (pdf && !failed) {
        return (
            <canvas
                ref={canvasRef}
                className={wide ? 'mx-auto max-h-full max-w-full object-contain' : 'h-full w-full object-cover'}
            />
        );
    }

    return (
        <div className="flex h-full w-full flex-col items-center justify-center gap-1 bg-emerald-50 text-primary">
            <span className="text-[10px] font-semibold uppercase tracking-[0.18em]">
                {(file.name.split('.').pop() || 'FILE').toUpperCase()}
            </span>
        </div>
    );
}
