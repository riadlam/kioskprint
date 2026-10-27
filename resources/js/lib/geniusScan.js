let sdkPromise;
let licensePromise;

function loadSdk() {
    if (!sdkPromise) {
        sdkPromise = import('@thegrizzlylabs/web-geniusscan-sdk').then((sdk) => {
            const key = import.meta.env.VITE_GENIUS_SCAN_LICENSE_KEY;

            if (key && !licensePromise) {
                licensePromise = sdk.setLicenseKey(key).catch((error) => {
                    console.error(error);
                });
            }

            return sdk;
        });
    }

    return sdkPromise;
}

export function prepareScanner() {
    const start = () => loadSdk();

    if (typeof requestIdleCallback === 'function') {
        requestIdleCallback(start, { timeout: 1200 });
        return;
    }

    start();
}

export async function scanPages(locale) {
    const { scanWithConfiguration } = await loadSdk();

    const result = await scanWithConfiguration({
        source: 'camera',
        multiPage: true,
        multiPageFormat: 'none',
        defaultFilter: 'none',
        jpegQuality: 100,
        language: locale === 'fr' ? 'fr' : 'ar',
        foregroundColor: '#ffffff',
        backgroundColor: '#022c22',
        highlightColor: '#6ee7b7',
    });

    const stamp = Date.now();

    return (result?.scans || []).map((scan, index) => new File(
        [scan.enhancedImage.data],
        `scan-${stamp}-${index + 1}.jpg`,
        { type: 'image/jpeg', lastModified: stamp },
    ));
}
