import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
    canvasToJpeg,
    cornersHoldStill,
    detectCorners,
    drawOutline,
    drawVideoFrame,
    insetCorners,
    loadScannerEngine,
    pageInsideFrame,
    renderScan,
    scaleCorners,
} from '../../lib/documentScan';

export default function DocumentScanner({ locale, copy, onClose, onPage }) {
    const videoRef = useRef(null);
    const overlayRef = useRef(null);
    const streamRef = useRef(null);
    const scannerRef = useRef(null);
    const loopRef = useRef(0);
    const watchRef = useRef(0);
    const capturingRef = useRef(false);
    const steadyRef = useRef({ count: 0, corners: null, width: 0 });
    const [phase, setPhase] = useState('camera');
    const [paperFound, setPaperFound] = useState(false);
    const [hold, setHold] = useState(0);
    const [flash, setFlash] = useState(false);
    const phaseRef = useRef(phase);
    phaseRef.current = phase;
    const [aspect, setAspect] = useState('3 / 4');

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
                loopRef.current = window.setTimeout(tick, 400);
                return;
            }

            const frame = drawVideoFrame(video, 480);
            overlay.width = frame.width;
            overlay.height = frame.height;
            const found = scannerRef.current ? detectCorners(frame) : null;
            drawOutline(overlay.getContext('2d'), found, frame.width, frame.height);
            setPaperFound((current) => (current === Boolean(found) ? current : Boolean(found)));
            if (noteSteady(found, frame.width, frame.height)) {
                capture();
                return;
            }
            loopRef.current = window.setTimeout(tick, 400);
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
            steadyRef.current = { count: 0, corners: null, width: 0 };
            setHold((current) => (current === 0 ? current : 0));
            return false;
        }

        const previous = steadyRef.current.corners;
        const still = previous && cornersHoldStill(previous, found, width, height);
        const count = still ? steadyRef.current.count + 1 : 1;
        steadyRef.current = { count, corners: found, width };
        setHold((current) => (current === count ? current : count));
        return count >= 4;
    }

    async function capture() {
        const video = videoRef.current;
        if (capturingRef.current || !video || !video.videoWidth) {
            return;
        }
        capturingRef.current = true;
        const steady = steadyRef.current;
        steadyRef.current = { count: 0, corners: null, width: 0 };
        setHold(0);

        const frame = drawVideoFrame(video, 1600);
        setFlash(true);
        window.setTimeout(() => setFlash(false), 140);
        stopWatching();
        setPhase('working');

        try {
            let corners = scannerRef.current ? detectCorners(frame) : null;
            if (!corners && steady.corners && steady.width) {
                corners = scaleCorners(steady.corners, frame.width / steady.width);
            }
            const page = corners || insetCorners(frame.width, frame.height);
            let canvas = frame;
            if (scannerRef.current) {
                try {
                    canvas = renderScan(frame, page);
                } catch {
                    canvas = frame;
                }
            }
            const file = await canvasToJpeg(canvas, `scan-${Date.now()}.jpg`);
            await onPage(file);
            onClose();
        } catch {
            setPhase('error');
        }
    }

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

            {phase === 'working' && (
                <Status text={copy.scanWorking} spin />
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
        </div>
    );

    return createPortal(shell, document.body);
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
