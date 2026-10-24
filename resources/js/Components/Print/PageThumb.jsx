import { useEffect, useRef, useState } from 'react';
import { copy } from '../../lib/printCopy';
import { loadPdfDocument, renderPdfPageToCanvas } from '../../lib/pdfPreview';

export default function PageThumb({ document, pageNumber, locale, size = 'sm' }) {
    const t = copy[locale];
    const holder = useRef(null);
    const canvasRef = useRef(null);
    const [visible, setVisible] = useState(false);
    const [failed, setFailed] = useState(false);
    const isImage = document.mime?.startsWith('image/');
    const large = size === 'lg';

    useEffect(() => {
        const node = holder.current;
        if (!node || isImage) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                }
            },
            { rootMargin: '280px' },
        );
        observer.observe(node);

        return () => observer.disconnect();
    }, [isImage]);

    useEffect(() => {
        if (!visible || isImage) {
            return undefined;
        }

        let cancelled = false;
        let task = null;

        (async () => {
            try {
                const pdf = await loadPdfDocument(document.url);
                if (cancelled || !canvasRef.current) {
                    return;
                }
                task = await renderPdfPageToCanvas(pdf, pageNumber, canvasRef.current, large ? 420 : 160);
            } catch (error) {
                if (!cancelled && error?.name !== 'RenderingCancelledException') {
                    setFailed(true);
                }
            }
        })();

        return () => {
            cancelled = true;
            task?.cancel?.();
        };
    }, [visible, isImage, document.url, pageNumber, large]);

    return (
        <div
            ref={holder}
            className={
                large
                    ? 'relative aspect-[3/4] w-full overflow-hidden bg-[#111827]'
                    : 'relative h-full w-full overflow-hidden bg-[#111827]'
            }
        >
            {isImage ? (
                <img src={document.url} alt="" className="h-full w-full bg-white object-contain" />
            ) : failed ? (
                <div className="flex h-full items-center justify-center bg-white px-2 text-center text-[10px] font-medium text-slate">
                    {t.fileFallback}
                </div>
            ) : (
                <canvas ref={canvasRef} className="absolute top-1/2 left-1/2 max-h-full max-w-full -translate-x-1/2 -translate-y-1/2 bg-white" />
            )}
        </div>
    );
}
