const PAPER_WIDTH = 1654;
const PAPER_HEIGHT = 2339;

let enginePromise = null;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[data-scanner-src="${src}"]`);
        if (existing) {
            if (existing.dataset.ready === '1') {
                resolve();
                return;
            }
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error(src)), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.dataset.scannerSrc = src;
        script.onload = () => {
            script.dataset.ready = '1';
            resolve();
        };
        script.onerror = () => reject(new Error(src));
        document.head.appendChild(script);
    });
}

function waitForOpenCv() {
    return new Promise((resolve, reject) => {
        const deadline = Date.now() + 30000;

        const finish = (cv) => {
            window.cv = cv;
            resolve(cv);
        };

        const check = () => {
            const cv = window.cv;
            if (cv && typeof cv.Mat === 'function') {
                finish(cv);
                return;
            }
            if (cv && typeof cv.then === 'function') {
                cv.then(finish).catch(reject);
                return;
            }
            if (Date.now() > deadline) {
                reject(new Error('opencv'));
                return;
            }
            window.setTimeout(check, 40);
        };

        check();
    });
}

export function loadScannerEngine() {
    if (!enginePromise) {
        enginePromise = (async () => {
            await loadScript('/scanner/opencv.js');
            await waitForOpenCv();
            await loadScript('/scanner/jscanify.js');
            return new window.jscanify();
        })().catch((error) => {
            enginePromise = null;
            throw error;
        });
    }

    return enginePromise;
}

export function insetCorners(width, height, margin = 0.08) {
    const x = width * margin;
    const y = height * margin;

    return {
        topLeftCorner: { x, y },
        topRightCorner: { x: width - x, y },
        bottomLeftCorner: { x, y: height - y },
        bottomRightCorner: { x: width - x, y: height - y },
    };
}

function distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

export function pageSize(corners) {
    const width = (
        distance(corners.topLeftCorner, corners.topRightCorner)
        + distance(corners.bottomLeftCorner, corners.bottomRightCorner)
    ) / 2;
    const height = (
        distance(corners.topLeftCorner, corners.bottomLeftCorner)
        + distance(corners.topRightCorner, corners.bottomRightCorner)
    ) / 2;

    if (width >= height) {
        return { width: PAPER_HEIGHT, height: PAPER_WIDTH };
    }

    return { width: PAPER_WIDTH, height: PAPER_HEIGHT };
}

function quadArea(corners) {
    const points = [
        corners.topLeftCorner,
        corners.topRightCorner,
        corners.bottomRightCorner,
        corners.bottomLeftCorner,
    ];
    let sum = 0;
    for (let index = 0; index < points.length; index += 1) {
        const current = points[index];
        const next = points[(index + 1) % points.length];
        sum += current.x * next.y - next.x * current.y;
    }

    return Math.abs(sum) / 2;
}

function cornersAreUsable(corners, width, height) {
    if (!corners?.topLeftCorner || !corners.topRightCorner || !corners.bottomLeftCorner || !corners.bottomRightCorner) {
        return false;
    }

    return quadArea(corners) >= width * height * 0.12;
}

export function drawVideoFrame(video, maxEdge) {
    const scale = Math.min(1, maxEdge / Math.max(video.videoWidth, video.videoHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
    canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
    canvas.getContext('2d', { willReadFrequently: true }).drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas;
}

export function detectCorners(scanner, canvas) {
    const img = window.cv.imread(canvas);
    try {
        const contour = scanner.findPaperContour(img);
        if (!contour) {
            return null;
        }
        const corners = scanner.getCornerPoints(contour);
        return cornersAreUsable(corners, canvas.width, canvas.height) ? corners : null;
    } catch {
        return null;
    } finally {
        img.delete();
    }
}

function applyLook(canvas, look) {
    const src = window.cv.imread(canvas);
    const gray = new window.cv.Mat();
    const out = document.createElement('canvas');

    try {
        window.cv.cvtColor(src, gray, window.cv.COLOR_RGBA2GRAY);
        if (look === 'photo') {
            window.cv.imshow(out, gray);
            return out;
        }

        const blur = new window.cv.Mat();
        const bw = new window.cv.Mat();
        try {
            window.cv.GaussianBlur(gray, blur, new window.cv.Size(3, 3), 0);
            window.cv.adaptiveThreshold(
                blur,
                bw,
                255,
                window.cv.ADAPTIVE_THRESH_GAUSSIAN_C,
                window.cv.THRESH_BINARY,
                61,
                12,
            );
            window.cv.imshow(out, bw);
        } finally {
            blur.delete();
            bw.delete();
        }

        return out;
    } finally {
        src.delete();
        gray.delete();
    }
}

export function rotateCanvas(source, turns) {
    const steps = ((turns % 4) + 4) % 4;
    if (steps === 0) {
        return source;
    }

    const canvas = document.createElement('canvas');
    const swap = steps % 2 === 1;
    canvas.width = swap ? source.height : source.width;
    canvas.height = swap ? source.width : source.height;
    const ctx = canvas.getContext('2d');
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate((steps * Math.PI) / 2);
    ctx.drawImage(source, -source.width / 2, -source.height / 2);

    return canvas;
}

export function renderScan(scanner, source, corners, look, turns) {
    const size = pageSize(corners);
    const warped = scanner.extractPaper(source, size.width, size.height, corners);
    if (!warped) {
        return null;
    }

    const filtered = applyLook(warped, look);
    return rotateCanvas(filtered, turns);
}

export function canvasToJpeg(canvas, name) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('jpeg'));
                return;
            }
            resolve(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
        }, 'image/jpeg', 0.85);
    });
}

export function drawOutline(ctx, corners, width, height) {
    ctx.clearRect(0, 0, width, height);
    if (!corners) {
        return;
    }

    const points = [
        corners.topLeftCorner,
        corners.topRightCorner,
        corners.bottomRightCorner,
        corners.bottomLeftCorner,
    ];
    ctx.save();
    ctx.fillStyle = 'rgba(2, 24, 18, 0.46)';
    ctx.fillRect(0, 0, width, height);
    ctx.globalCompositeOperation = 'destination-out';
    traceQuad(ctx, points);
    ctx.fill();
    ctx.restore();

    ctx.save();
    ctx.fillStyle = 'rgba(110, 231, 183, 0.18)';
    traceQuad(ctx, points);
    ctx.fill();
    ctx.strokeStyle = '#6ee7b7';
    ctx.lineWidth = Math.max(3, width / 110);
    ctx.lineJoin = 'round';
    ctx.stroke();
    ctx.strokeStyle = '#ecfdf5';
    ctx.lineWidth = Math.max(5, width / 70);
    ctx.lineCap = 'round';
    points.forEach((point, index) => {
        const previous = points[(index + 3) % points.length];
        const next = points[(index + 1) % points.length];
        const arm = Math.max(18, width / 14);
        ctx.beginPath();
        ctx.moveTo(point.x + unit(previous, point).x * arm, point.y + unit(previous, point).y * arm);
        ctx.lineTo(point.x, point.y);
        ctx.lineTo(point.x + unit(next, point).x * arm, point.y + unit(next, point).y * arm);
        ctx.stroke();
    });
    ctx.restore();
}

function traceQuad(ctx, points) {
    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    points.slice(1).forEach((point) => ctx.lineTo(point.x, point.y));
    ctx.closePath();
}

function unit(from, to) {
    const length = Math.hypot(from.x - to.x, from.y - to.y) || 1;
    return { x: (from.x - to.x) / length, y: (from.y - to.y) / length };
}
