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
            await loadScript('/scanner/jscanify.js');
            if (typeof window.jscanify !== 'function') {
                throw new Error('jscanify');
            }
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

function solve(rows) {
    const n = rows.length;
    const matrix = rows.map((row) => row.slice());

    for (let col = 0; col < n; col += 1) {
        let pivot = col;
        for (let row = col + 1; row < n; row += 1) {
            if (Math.abs(matrix[row][col]) > Math.abs(matrix[pivot][col])) {
                pivot = row;
            }
        }
        [matrix[col], matrix[pivot]] = [matrix[pivot], matrix[col]];
        const scale = matrix[col][col] || 1e-12;
        for (let colIndex = col; colIndex <= n; colIndex += 1) {
            matrix[col][colIndex] /= scale;
        }
        for (let row = 0; row < n; row += 1) {
            if (row === col) {
                continue;
            }
            const factor = matrix[row][col];
            for (let colIndex = col; colIndex <= n; colIndex += 1) {
                matrix[row][colIndex] -= factor * matrix[col][colIndex];
            }
        }
    }

    return matrix.map((row) => row[n]);
}

function homography(from, to) {
    const rows = [];
    from.forEach((point, index) => {
        const x = point.x;
        const y = point.y;
        const X = to[index].x;
        const Y = to[index].y;
        rows.push([x, y, 1, 0, 0, 0, -X * x, -X * y, X]);
        rows.push([0, 0, 0, x, y, 1, -Y * x, -Y * y, Y]);
    });
    const h = solve(rows);
    h.push(1);
    return h;
}

function applyHomography(h, x, y) {
    const weight = h[6] * x + h[7] * y + h[8];
    return {
        x: (h[0] * x + h[1] * y + h[2]) / weight,
        y: (h[3] * x + h[4] * y + h[5]) / weight,
    };
}

export function projectPoint(from, to, x, y) {
    return applyHomography(homography(from, to), x, y);
}

function warpCanvas(source, corners, width, height) {
    const srcImage = source.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, source.width, source.height);
    const out = document.createElement('canvas');
    out.width = width;
    out.height = height;
    const dst = out.getContext('2d').createImageData(width, height);
    const map = homography(
        [
            { x: 0, y: 0 },
            { x: width, y: 0 },
            { x: width, y: height },
            { x: 0, y: height },
        ],
        [
            corners.topLeftCorner,
            corners.topRightCorner,
            corners.bottomRightCorner,
            corners.bottomLeftCorner,
        ],
    );
    const sourcePixels = srcImage.data;
    const destPixels = dst.data;
    const sourceWidth = source.width;
    const sourceHeight = source.height;

    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            const point = applyHomography(map, x, y);
            const sx = point.x | 0;
            const sy = point.y | 0;
            const destIndex = (y * width + x) * 4;
            destPixels[destIndex + 3] = 255;
            if (sx < 0 || sy < 0 || sx >= sourceWidth || sy >= sourceHeight) {
                destPixels[destIndex] = 255;
                destPixels[destIndex + 1] = 255;
                destPixels[destIndex + 2] = 255;
                continue;
            }
            const sourceIndex = (sy * sourceWidth + sx) * 4;
            destPixels[destIndex] = sourcePixels[sourceIndex];
            destPixels[destIndex + 1] = sourcePixels[sourceIndex + 1];
            destPixels[destIndex + 2] = sourcePixels[sourceIndex + 2];
        }
    }

    out.getContext('2d').putImageData(dst, 0, 0);
    return out;
}

function applyLook(canvas, look) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const pixels = image.data;

    for (let index = 0; index < pixels.length; index += 4) {
        const gray = 0.299 * pixels[index] + 0.587 * pixels[index + 1] + 0.114 * pixels[index + 2];
        const value = look === 'photo'
            ? gray
            : (gray > 168 ? 255 : gray < 112 ? 0 : ((gray - 112) * 255) / 56);
        pixels[index] = value;
        pixels[index + 1] = value;
        pixels[index + 2] = value;
    }

    ctx.putImageData(image, 0, 0);
    return canvas;
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

export function renderScan(source, corners, look, turns) {
    const size = pageSize(corners);
    const warped = warpCanvas(source, corners, size.width, size.height);
    return rotateCanvas(applyLook(warped, look), turns);
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
