const PAPER_WIDTH = 1100;
const PAPER_HEIGHT = 1556;

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
        let settled = false;
        let hooked = false;
        const deadline = window.setTimeout(() => {
            if (settled) {
                return;
            }
            settled = true;
            reject(new Error('opencv'));
        }, 30000);

        const finish = (cv) => {
            if (settled) {
                return;
            }
            const ready = cv || window.cv;
            if (!ready || typeof ready.Mat !== 'function') {
                window.setTimeout(check, 40);
                return;
            }
            settled = true;
            window.clearTimeout(deadline);
            window.cv = ready;
            resolve(ready);
        };

        const check = () => {
            if (settled) {
                return;
            }
            const cv = window.cv;
            if (cv && typeof cv.Mat === 'function') {
                finish(cv);
                return;
            }
            if (cv && typeof cv.then === 'function' && !hooked) {
                hooked = true;
                cv.then((ready) => finish(ready));
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
            return window.cv;
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

const CORNER_KEYS = ['topLeftCorner', 'topRightCorner', 'bottomRightCorner', 'bottomLeftCorner'];

export function pageInsideFrame(corners, width, height) {
    const margin = Math.min(width, height) * 0.02;
    return CORNER_KEYS.every((key) => {
        const point = corners[key];
        return point.x > margin && point.y > margin && point.x < width - margin && point.y < height - margin;
    });
}

export function cornersHoldStill(previous, next, width, height) {
    const limit = Math.hypot(width, height) * 0.02;
    return CORNER_KEYS.every((key) => Math.hypot(previous[key].x - next[key].x, previous[key].y - next[key].y) <= limit);
}

export function orderQuad(points) {
    const scored = points.map((point) => ({
        point: { x: point.x, y: point.y },
        sum: point.x + point.y,
        diff: point.y - point.x,
    }));

    return {
        topLeftCorner: scored.reduce((best, item) => (item.sum < best.sum ? item : best)).point,
        bottomRightCorner: scored.reduce((best, item) => (item.sum > best.sum ? item : best)).point,
        topRightCorner: scored.reduce((best, item) => (item.diff < best.diff ? item : best)).point,
        bottomLeftCorner: scored.reduce((best, item) => (item.diff > best.diff ? item : best)).point,
    };
}

export function scaleCorners(corners, scale) {
    return CORNER_KEYS.reduce((next, key) => {
        next[key] = { x: corners[key].x * scale, y: corners[key].y * scale };
        return next;
    }, {});
}

function readPoints(mat) {
    const data = mat.data32S;
    const points = [];
    for (let index = 0; index < data.length; index += 2) {
        points.push({ x: data[index], y: data[index + 1] });
    }
    return points;
}

function release(mat) {
    if (mat && typeof mat.delete === 'function') {
        mat.delete();
    }
}

export function detectCorners(canvas) {
    const cv = window.cv;
    if (!cv || typeof cv.Mat !== 'function' || !canvas) {
        return null;
    }

    const img = cv.imread(canvas);
    const gray = new cv.Mat();
    const blur = new cv.Mat();
    const edges = new cv.Mat();
    const contours = new cv.MatVector();
    const hierarchy = new cv.Mat();
    const approx = new cv.Mat();
    let best = null;

    try {
        cv.cvtColor(img, gray, cv.COLOR_RGBA2GRAY);
        cv.GaussianBlur(gray, blur, new cv.Size(5, 5), 0);
        cv.Canny(blur, edges, 75, 200);
        cv.findContours(edges, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);

        let bestArea = 0;
        for (let index = 0; index < contours.size(); index += 1) {
            const item = contours.get(index);
            const area = cv.contourArea(item);
            if (area > bestArea) {
                release(best);
                best = item.clone();
                bestArea = area;
            }
            item.delete();
        }

        if (!best || bestArea < canvas.width * canvas.height * 0.12) {
            return null;
        }

        const perimeter = cv.arcLength(best, true);
        let points = null;
        [0.02, 0.04, 0.06, 0.08].some((factor) => {
            cv.approxPolyDP(best, approx, factor * perimeter, true);
            if (approx.rows === 4) {
                points = readPoints(approx);
                return true;
            }
            return false;
        });

        if (!points && cv.RotatedRect?.points) {
            points = cv.RotatedRect.points(cv.minAreaRect(best)).map((point) => ({ x: point.x, y: point.y }));
        }
        if (!points || points.length !== 4) {
            return null;
        }

        const corners = orderQuad(points);
        return cornersAreUsable(corners, canvas.width, canvas.height) ? corners : null;
    } catch {
        return null;
    } finally {
        release(img);
        release(gray);
        release(blur);
        release(edges);
        release(hierarchy);
        release(contours);
        release(approx);
        release(best);
    }
}

function warpWithOpenCv(source, corners, width, height) {
    const cv = window.cv;
    const img = cv.imread(source);
    const srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
        corners.topLeftCorner.x, corners.topLeftCorner.y,
        corners.topRightCorner.x, corners.topRightCorner.y,
        corners.bottomLeftCorner.x, corners.bottomLeftCorner.y,
        corners.bottomRightCorner.x, corners.bottomRightCorner.y,
    ]);
    const dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
        0, 0,
        width, 0,
        0, height,
        width, height,
    ]);
    const transform = cv.getPerspectiveTransform(srcTri, dstTri);
    const warped = new cv.Mat();
    const gray = new cv.Mat();
    const page = new cv.Mat();

    try {
        cv.warpPerspective(
            img,
            warped,
            transform,
            new cv.Size(width, height),
            cv.INTER_LINEAR,
            cv.BORDER_CONSTANT,
            new cv.Scalar(255, 255, 255, 255),
        );
        cv.cvtColor(warped, gray, cv.COLOR_RGBA2GRAY);
        cv.adaptiveThreshold(gray, page, 255, cv.ADAPTIVE_THRESH_GAUSSIAN_C, cv.THRESH_BINARY, 31, 12);
        const out = document.createElement('canvas');
        cv.imshow(out, page);
        return out;
    } finally {
        release(img);
        release(srcTri);
        release(dstTri);
        release(transform);
        release(warped);
        release(gray);
        release(page);
    }
}

export function renderScan(source, corners) {
    const size = pageSize(corners);
    return warpWithOpenCv(source, corners, size.width, size.height);
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
