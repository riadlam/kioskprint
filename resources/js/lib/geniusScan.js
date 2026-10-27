export async function scanPages(locale) {
    const { setLicenseKey, scanWithConfiguration } = await import('@thegrizzlylabs/web-geniusscan-sdk');
    const key = import.meta.env.VITE_GENIUS_SCAN_LICENSE_KEY;

    if (key) {
        await setLicenseKey(key);
    }

    const result = await scanWithConfiguration({
        source: 'camera',
        multiPage: true,
        multiPageFormat: 'none',
        defaultFilter: 'strong_monochrome',
        jpegQuality: 80,
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
