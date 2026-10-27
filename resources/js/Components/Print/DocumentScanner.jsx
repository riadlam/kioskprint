import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
    canvasToJpeg,
    cornersHoldStill,
    detectCorners,
    drawOutline,
    drawVideoFrame,
    insetCorners,
    pageInsideFrame,
    loadScannerEngine,
    renderScan,
} from '../../lib/documentScan';

const CORNER_KEYS = ['topLeftCorner', 'topRightCorner', 'bottomRightCorner', 'bottomLeftCorner'];

export default function DocumentScanner({ locale, copy, room, onClose, onPage }) {
    const videoRef = useRef(null);
    const overlayRef = useRef(null);
    const streamRef = useRef(null);
    const scannerRef = useRef(null);
    const sourceRef = useRef(null);
    const resultRef = useRef(null);
    const loopRef = useRef(0);
    const watchRef = useRef(0);
    const publishingRef = useRef(false);
    const capturingRef = useRef(false);
    const steadyRef = useRef({ count: 0, corners: null });
    const [phase, setPhase] = useState('camera');
    const [paperFound, setPaperFound] = useState(false);
    const [hold, setHold] = useState(0);
    const [flash, setFlash] = useState(false);
    const phaseRef = useRef(phase);
    phaseRef.current = phase;
    const [aspect, setAspect] = useState('3 / 4');
    const [look, setLook] = useState('document');
    const [turns, setTurns] = useState(0);
    const [corners, setCorners] = useState(null);
    const [previewUrl, setPreviewUrl] = useState('');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previous;
        };
    }, []);

    useEffect(() => () => {
        window.clearTimeout(loopRef.current);
        stopStream(streamRef.current);
    }, []);

    useEffect(() => {
        if (phase !== 'camera') {
            return undefined;
        }

        let cancelled = false;
        openCamera()
            .then(() => {
                if (cancelled) {
                    return;
                }
                loadScannerEngine()
                    .then((scanner) => {
                        if (!cancelled) {
                            scannerRef.current = scanner;
                        }
                    })
                    .catch(() => {});
            })
            .catch((error) => {
                if (cancelled) {
                    return;
                }
                setPhase(error?.name === 'NotAllowedError' || error?.name === 'NotFoundError' ? 'denied' : 'error');
            });

        return () => {
            cancelled = true;
            watchRef.current += 1;
            window.clearTimeout(loopRef.current);
            stopStream(streamRef.current);
            streamRef.current = null;
        };
    }, [phase]);

    useEffect(() => {
        if (phase !== 'preview' || !corners || !sourceRef.current) {
            return undefined;
        }

        let cancelled = false;
        setBusy(true);
        const timer = window.setTimeout(() => {
            try {
                const canvas = renderScan(sourceRef.current, corners, look, turns);
                if (!canvas || cancelled) {
                    return;
                }
                resultRef.current = canvas;
                canvas.toBlob((blob) => {
                    if (!blob || cancelled) {
                        return;
                    }
                    const url = URL.createObjectURL(blob);
                    setPreviewUrl((current) => {
                        if (current) {
                            URL.revokeObjectURL(current);
                        }
                        return url;
                    });
                    setBusy(false);
                }, 'image/jpeg', 0.85);
            } catch {
                if (!cancelled) {
                    setBusy(false);
                    setMessage(copy.scanFailed);
                }
            }
        }, 40);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [phase, corners, look, turns, copy.scanFailed]);

    async function openCamera() {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
        });
        if (phaseRef.current !== 'camera') {
            stopStream(stream);
            return;
        }
        streamRef.current = stream;
        const video = videoRef.current;
        if (!video) {
            stopStream(stream);
            return;
        }
        video.srcObject = stream;
        await video.play();
        if (phaseRef.current !== 'camera') {
            stopStream(stream);
            return;
        }
        setAspect(`${video.videoWidth} / ${video.videoHeight}`);
        watchPaper();
    }

    function watchPaper() {
        window.clearTimeout(loopRef.current);
        const watchId = watchRef.current + 1;
        watchRef.current = watchId;
        const tick = () => {
            if (watchRef.current !== watchId) {
                return;
            }
            const video = videoRef.current;
            const overlay = overlayRef.current;
            if (!video || !overlay || video.readyState < 2) {
                loopRef.current = window.setTimeout(tick, 280);
                return;
            }

            const frame = drawVideoFrame(video, 480);
            overlay.width = frame.width;
            overlay.height = frame.height;
            const scanner = scannerRef.current;
            const found = scanner ? detectCorners(scanner, frame) : null;
            drawOutline(overlay.getContext('2d'), found, frame.width, frame.height);
            setPaperFound((current) => (current === Boolean(found) ? current : Boolean(found)));
            const ready = noteSteady(found, frame.width, frame.height);
            if (ready) {
                capture();
                return;
            }
            loopRef.current = window.setTimeout(tick, 280);
        };
        tick();
    }

    function stopWatching() {
        watchRef.current += 1;
        window.clearTimeout(loopRef.current);
        stopStream(streamRef.current);
        streamRef.current = null;
    }

    function noteSteady(found, width, height) {
        if (!found || !pageInsideFrame(found, width, height)) {
            steadyRef.current = { count: 0, corners: null };
            setHold((current) => (current === 0 ? current : 0));
            return false;
        }

        const previous = steadyRef.current.corners;
        const still = previous && cornersHoldStill(previous, found, width, height);
        const count = still ? steadyRef.current.count + 1 : 1;
        steadyRef.current = { count, corners: found };
        setHold((current) => (current === count ? current : count));
        return count >= 4;
    }

    function capture() {
        const video = videoRef.current;
        if (capturingRef.current || !video || !video.videoWidth) {
            return;
        }
        capturingRef.current = true;
        steadyRef.current = { count: 0, corners: null };
        setHold(0);

        const frame = drawVideoFrame(video, 1600);
        setFlash(true);
        window.setTimeout(() => setFlash(false), 140);
        stopWatching();
        sourceRef.current = frame;
        const scanner = scannerRef.current;
        const found = scanner ? detectCorners(scanner, frame) : null;
        const next = found || insetCorners(frame.width, frame.height);
        setCorners(next);
        setTurns(0);
        setLook('document');
        setMessage(found ? '' : copy.scanAdjust);
        setPhase(found ? 'preview' : 'corners');
    }

    async function publish() {
        const canvas = resultRef.current;
        if (!canvas || publishingRef.current) {
            return null;
        }
        publishingRef.current = true;
        setBusy(true);
        try {
            const file = await canvasToJpeg(canvas, `scan-${Date.now()}.jpg`);
            await onPage(file);
            return file;
        } finally {
            publishingRef.current = false;
            setBusy(false);
        }
    }

    async function addAndClose() {
        const file = await publish();
        if (file) {
            onClose();
        }
    }

    async function addAndContinue() {
        const file = await publish();
        if (!file || room <= 1) {
            if (file) {
                onClose();
            }
            return;
        }
        resultRef.current = null;
        sourceRef.current = null;
        setCorners(null);
        setMessage('');
        setPaperFound(false);
        setHold(0);
        capturingRef.current = false;
        steadyRef.current = { count: 0, corners: null };
        setPreviewUrl((current) => {
            if (current) {
                URL.revokeObjectURL(current);
            }
            return '';
        });
        setPhase('camera');
    }

    const canTakeMore = room > 1;
    const shell = (
        <div
            dir={locale === 'ar' ? 'rtl' : 'ltr'}
            className="fixed inset-0 z-[80] flex flex-col bg-emerald-950 text-white"
        >
            <header className="flex items-center justify-between px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                <h2 className="text-[15px] font-semibold">{copy.scanTitle}</h2>
                <button type="button" onClick={onClose} className="border border-white/25 px-3 py-2 text-[11px] font-semibold">
                    {copy.scanClose}
                </button>
            </header>

            {(phase === 'denied' || phase === 'error') && (
                <Status text={phase === 'denied' ? copy.scanCameraDenied : copy.scanFailed} />
            )}

            {phase === 'camera' && (
                <>
                    <div className="flex min-h-0 flex-1 items-center justify-center px-4">
                        <div className="relative h-full max-h-full max-w-full" style={{ aspectRatio: aspect }}>
                            <video ref={videoRef} autoPlay playsInline muted className="h-full w-full bg-black object-contain" />
                            <canvas ref={overlayRef} className="pointer-events-none absolute inset-0 h-full w-full" />
                            {!paperFound && (
                                <span className="animate-scanline pointer-events-none absolute inset-x-[8%] h-1 bg-emerald-200 shadow-[0_0_18px_4px_rgba(110,231,183,0.85)]" />
                            )}
                            {flash && <span className="pointer-events-none absolute inset-0 bg-white" />}
                        </div>
                    </div>
                    <p className="px-6 pt-3 text-center text-[12px] text-white/75">{hold >= 2 ? copy.scanSteady : copy.scanHint}</p>
                    <div className="flex justify-center px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4">
                        <button
                            type="button"
                            onClick={capture}
                            className="h-16 w-16 rounded-full border-4 border-white"
                            style={{ backgroundColor: `rgba(255,255,255,${0.2 + Math.min(hold, 4) * 0.2})` }}
                            aria-label={copy.scanCapture}
                        />
                    </div>
                </>
            )}

            {(phase === 'preview' || phase === 'corners') && (
                <>
                    <div className="flex min-h-0 flex-1 items-center justify-center px-4">
                        {phase === 'preview' ? (
                            <div className="relative flex h-full max-h-full w-full items-center justify-center">
                                {previewUrl ? (
                                    <img src={previewUrl} alt="" className="max-h-full max-w-full bg-white object-contain" />
                                ) : null}
                                {busy && (
                                    <p className="absolute bottom-3 bg-emerald-950/80 px-3 py-1 text-[11px]">{copy.scanWorking}</p>
                                )}
                            </div>
                        ) : (
                            <CornerEditor
                                source={sourceRef.current}
                                corners={corners}
                                hint={copy.scanAdjust}
                                onChange={setCorners}
                            />
                        )}
                    </div>
                    {message && phase === 'corners' ? null : message && (
                        <p className="px-6 pt-2 text-center text-[12px] text-amber-100">{message}</p>
                    )}
                    <div className="space-y-3 px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3">
                        <div className="grid grid-cols-3 gap-2">
                            <Choice active={look === 'document'} onClick={() => setLook('document')}>{copy.scanDocument}</Choice>
                            <Choice active={look === 'photo'} onClick={() => setLook('photo')}>{copy.scanPhoto}</Choice>
                            <Choice onClick={() => setTurns((current) => (current + 1) % 4)}>{copy.scanRotate}</Choice>
                        </div>
                        <div className={`grid gap-2 ${canTakeMore ? 'grid-cols-2' : 'grid-cols-1'}`}>
                            {phase === 'preview' ? (
                                <button type="button" onClick={() => setPhase('corners')} className="h-12 border border-white/30 text-[13px] font-semibold">
                                    {copy.scanCorners}
                                </button>
                            ) : (
                                <button type="button" onClick={() => setPhase('preview')} className="h-12 border border-white/30 text-[13px] font-semibold">
                                    {copy.scanApply}
                                </button>
                            )}
                            {canTakeMore && (
                                <button
                                    type="button"
                                    onClick={addAndContinue}
                                    disabled={busy || !previewUrl}
                                    className="h-12 border border-white/30 text-[13px] font-semibold disabled:opacity-40"
                                >
                                    {copy.scanAgain}
                                </button>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={addAndClose}
                            disabled={busy || !previewUrl}
                            className="h-12 w-full bg-white text-[13px] font-semibold text-emerald-950 disabled:opacity-40"
                        >
                            {copy.scanDone}
                        </button>
                    </div>
                </>
            )}
        </div>
    );

    return createPortal(shell, document.body);
}

function CornerEditor({ source, corners, hint, onChange }) {
    const boxRef = useRef(null);
    const draftRef = useRef(corners);
    const [draft, setDraft] = useState(corners);
    const url = useMemoUrl(source);

    useEffect(() => {
        draftRef.current = corners;
        setDraft(corners);
    }, [corners]);

    if (!source || !draft || !url) {
        return null;
    }

    function move(key, event) {
        const rect = boxRef.current?.getBoundingClientRect();
        if (!rect) {
            return;
        }
        const x = Math.min(source.width, Math.max(0, ((event.clientX - rect.left) / rect.width) * source.width));
        const y = Math.min(source.height, Math.max(0, ((event.clientY - rect.top) / rect.height) * source.height));
        const next = { ...draftRef.current, [key]: { x, y } };
        draftRef.current = next;
        setDraft(next);
    }

    return (
        <div className="flex h-full w-full flex-col items-center justify-center">
            <div ref={boxRef} dir="ltr" className="relative max-h-full max-w-full" style={{ aspectRatio: `${source.width} / ${source.height}` }}>
                <img src={url} alt="" className="h-full w-full object-contain" />
                <svg className="pointer-events-none absolute inset-0 h-full w-full" viewBox={`0 0 ${source.width} ${source.height}`}>
                    <polygon
                        points={CORNER_KEYS.map((key) => `${draft[key].x},${draft[key].y}`).join(' ')}
                        fill="none"
                        stroke="#6ee7b7"
                        strokeWidth={Math.max(4, source.width / 180)}
                    />
                </svg>
                {CORNER_KEYS.map((key) => (
                    <button
                        key={key}
                        type="button"
                        aria-label={key}
                        className="absolute h-8 w-8 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-emerald-950 bg-emerald-200"
                        style={{ left: `${(draft[key].x / source.width) * 100}%`, top: `${(draft[key].y / source.height) * 100}%` }}
                        onPointerDown={(event) => event.currentTarget.setPointerCapture(event.pointerId)}
                        onPointerMove={(event) => {
                            if (event.currentTarget.hasPointerCapture(event.pointerId)) {
                                move(key, event);
                            }
                        }}
                        onPointerUp={() => onChange(draftRef.current)}
                    />
                ))}
            </div>
            <p className="px-6 pt-3 text-center text-[12px] text-white/75">{hint}</p>
        </div>
    );
}

function useMemoUrl(source) {
    const [url, setUrl] = useState('');

    useEffect(() => {
        if (!source) {
            return undefined;
        }
        const next = source.toDataURL('image/jpeg', 0.8);
        setUrl(next);
        return undefined;
    }, [source]);

    return url;
}

function Choice({ active = false, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`h-11 text-[12px] font-semibold ${active ? 'bg-white text-emerald-950' : 'border border-white/30 text-white'}`}
        >
            {children}
        </button>
    );
}

function Status({ text, spin = false }) {
    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-4 px-8 text-center">
            {spin && <span className="h-8 w-8 animate-spin rounded-full border-2 border-white/25 border-t-white" />}
            <p className="text-[14px] leading-6 text-white/85">{text}</p>
        </div>
    );
}

function stopStream(stream) {
    stream?.getTracks().forEach((track) => track.stop());
}
