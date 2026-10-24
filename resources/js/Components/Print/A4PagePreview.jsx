import { useEffect, useRef, useState } from 'react';
import { copy } from '../../lib/printCopy';
import { A4_RATIO, loadPdfDocument, renderPdfPageAsA4 } from '../../lib/pdfPreview';

/**
 * Active-page viewer: ISO A4 sheet framed with pdf.js rendering.
 */
export default function A4PagePreview({ document, pageNumber, locale }) {
    const t = copy[locale];
    const stageRef = useRef(null);
    const canvasRef = useRef(null);
    const [failed, setFailed] = useState(false);
    const [loading, setLoading] = useState(true);
    const isImage = document.mime?.startsWith('image/');

    useEffect(() => {
        if (isImage) {
            setLoading(false);
            setFailed(false);
            return undefined;
        }

        const stage = stageRef.current;
        const canvas = canvasRef.current;
        if (!stage || !canvas) {
            return undefined;
        }

        let cancelled = false;
        let frame = 0;
        let generation = 0;

        const paint = async () => {
            const width = stage.clientWidth;
            const height = stage.clientHeight;
            if (width < 8 || height < 8) {
                return;
            }

            const current = ++generation;
            setLoading(true);
            setFailed(false);

            try {
                const pdf = await loadPdfDocument(document.url);
                if (cancelled || current !== generation) {
                    return;
                }
                await renderPdfPageAsA4(pdf, pageNumber, canvas, width, height);
                if (!cancelled && current === generation) {
                    setLoading(false);
                }
            } catch (error) {
                if (!cancelled && current === generation && error?.name !== 'RenderingCancelledException') {
                    setFailed(true);
                    setLoading(false);
                }
            }
        };

        const schedule = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                paint();
            });
        };

        schedule();
        const observer = new ResizeObserver(schedule);
        observer.observe(stage);

        return () => {
            cancelled = true;
            cancelAnimationFrame(frame);
            observer.disconnect();
        };
    }, [document.url, pageNumber, isImage]);

    return (
        <div className="absolute inset-0 p-2 [container-type:size]">
            <div
                className="a4-sheet absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white"
                style={{
                    aspectRatio: String(A4_RATIO),
                    width: `min(100cqw, calc(100cqh * ${A4_RATIO}))`,
                }}
            >
                <div ref={stageRef} className="absolute inset-0 overflow-hidden bg-white">
                    {failed ? (
                        <div className="flex h-full items-center justify-center px-3 text-center text-[11px] font-medium text-slate">
                            {t.fileFallback}
                        </div>
                    ) : isImage ? (
                        <img
                            src={document.url}
                            alt=""
                            className="h-full w-full object-contain object-center"
                            onLoad={() => setLoading(false)}
                            onError={() => {
                                setFailed(true);
                                setLoading(false);
                            }}
                        />
                    ) : (
                        <canvas ref={canvasRef} className="absolute top-1/2 left-1/2 block -translate-x-1/2 -translate-y-1/2" />
                    )}

                    {loading && !failed && (
                        <div className="absolute inset-0 flex items-center justify-center bg-white/70">
                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-emerald-200 border-t-primary" />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
