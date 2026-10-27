import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AccessSheet from '../../Components/Print/AccessSheet';
import DocumentScanner from '../../Components/Print/DocumentScanner';
import LocalAssetPreview from '../../Components/Print/LocalAssetPreview';
import PhoneShell from '../../Components/Print/PhoneShell';
import { copy } from '../../lib/printCopy';
import {
    ACCEPT,
    countPdfPages,
    formatSize,
    isAndroid,
    isPdfFile,
    isSupported,
    isTooBig,
    makeItemId,
    pickPrintFiles,
} from '../../lib/printFiles';

const ACCESS_KEY = 'metbati.file-access';
const LOCALE_KEY = 'metbati.print.locale';

export default function Select({ limits }) {
    const fileInputRef = useRef(null);
    const [locale, setLocale] = useState(() => sessionStorage.getItem(LOCALE_KEY) || 'ar');
    const [items, setItems] = useState([]);
    const [activeId, setActiveId] = useState(null);
    const [notice, setNotice] = useState('');
    const [sheet, setSheet] = useState(null);
    const [accessGranted, setAccessGranted] = useState(() => sessionStorage.getItem(ACCESS_KEY) === '1');
    const [scanning, setScanning] = useState(false);
    const form = useForm({ locale: 'ar', files: [] });
    const t = copy[locale];
    const mb = Math.round(limits.max_kilobytes / 1024);

    const selected = useMemo(() => items.filter((item) => item.selected), [items]);
    const active = items.find((item) => item.id === activeId) || selected[0] || items[0] || null;
    const reading = items.some((item) => item.status === 'reading');
    const pageTotal = selected.reduce((sum, item) => sum + (item.pages || 0), 0);

    useEffect(() => {
        if (!activeId && items[0]) {
            setActiveId(items[0].id);
        }
    }, [items, activeId]);

    function changeLocale(next) {
        sessionStorage.setItem(LOCALE_KEY, next);
        setLocale(next);
    }

    function requestAccess() {
        if (!accessGranted || (isAndroid() && !sessionStorage.getItem(ACCESS_KEY))) {
            setSheet('ask');
            return;
        }
        openPicker();
    }

    async function openPicker() {
        sessionStorage.setItem(ACCESS_KEY, '1');
        setAccessGranted(true);
        setSheet(null);

        const result = await pickPrintFiles(fileInputRef.current);
        if (result.permission === 'denied') {
            sessionStorage.removeItem(ACCESS_KEY);
            setAccessGranted(false);
            setSheet('denied');
            return;
        }

        await acceptFiles(result.files);
    }

    async function acceptFiles(files) {
        if (!files.length) {
            return;
        }

        const skipped = files.filter((file) => !isSupported(file));
        const oversized = files.filter((file) => isSupported(file) && isTooBig(file));
        const accepted = files.filter((file) => isSupported(file) && !isTooBig(file));
        const room = Math.max(0, limits.max_files - items.length);
        const nextFiles = accepted.slice(0, room);

        if (skipped.length) {
            setNotice(t.unsupported);
        } else if (oversized.length) {
            setNotice(t.tooBig);
        } else {
            setNotice('');
        }

        if (!nextFiles.length) {
            return;
        }

        const entries = nextFiles.map((file) => ({
            id: makeItemId(),
            file,
            selected: true,
            pages: isPdfFile(file) ? null : 1,
            status: isPdfFile(file) ? 'reading' : 'ready',
        }));

        setItems((current) => [...current, ...entries]);
        setActiveId(entries[0].id);

        const queue = entries.filter((entry) => entry.status === 'reading');
        let cursor = 0;
        async function worker() {
            while (cursor < queue.length) {
                const entry = queue[cursor];
                cursor += 1;
                try {
                    const pages = await countPdfPages(entry.file);
                    setItems((current) => current.map((item) => (
                        item.id === entry.id ? { ...item, pages, status: 'ready' } : item
                    )));
                } catch {
                    setItems((current) => current.map((item) => (
                        item.id === entry.id ? { ...item, pages: 1, status: 'ready' } : item
                    )));
                }
            }
        }
        await Promise.all([worker(), worker()]);
    }

    function focusItem(id) {
        setActiveId(id);
    }

    function toggleSelect(id) {
        setItems((current) => current.map((item) => (
            item.id === id ? { ...item, selected: !item.selected } : item
        )));
        setActiveId(id);
    }

    function removeItem(id) {
        setItems((current) => {
            const next = current.filter((item) => item.id !== id);
            if (activeId === id) {
                setActiveId(next[0]?.id || null);
            }
            return next;
        });
    }

    function submit() {
        const files = selected.map((item) => item.file);
        if (!files.length) {
            return;
        }
        form.transform((data) => ({ ...data, locale, files }));
        form.post('/print', { forceFormData: true });
    }

    const serverErrors = Object.values(form.errors);
    const canContinue = selected.length > 0 && !reading && !form.processing;
    const canScan = items.length < limits.max_files;
    const cta = form.processing
        ? t.uploading
        : reading
            ? t.reading
            : selected.length
                ? t.nextSelected(selected.length)
                : t.next;

    return (
        <PhoneShell
            locale={locale}
            onLocale={changeLocale}
            step={1}
            dense
            footer={(
                <div className="border-t border-emerald-900/10 bg-white px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <div className="mb-2 flex items-center justify-between gap-3 text-[11px] font-medium text-slate">
                        <span>{t.selectedCount(selected.length)}</span>
                        <span>{pageTotal ? t.pages(pageTotal) : t.formats}</span>
                    </div>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={!canContinue}
                        className="h-12 w-full bg-primary text-[13px] font-semibold tracking-[0.04em] text-white transition disabled:bg-emerald-100 disabled:text-emerald-400"
                    >
                        {form.processing && form.progress ? `${cta} ${form.progress.percentage}%` : cta}
                    </button>
                </div>
            )}
        >
            <Head title={t.chooseTitle} />

            <input
                ref={fileInputRef}
                type="file"
                multiple
                accept={ACCEPT}
                className="sr-only"
                tabIndex={-1}
            />

            <section className="relative flex h-[38dvh] min-h-[200px] flex-col border-b border-emerald-900/10 bg-primary">
                <div className="pointer-events-none absolute inset-0 print-studio-grid opacity-50" />
                <div className="relative z-10 flex items-center justify-between px-4 py-3">
                    <div>
                        <h1 className="text-[15px] font-semibold tracking-tight text-white">{t.chooseTitle}</h1>
                        <p className="mt-0.5 text-[11px] text-white/75">{t.chooseHint}</p>
                    </div>
                    <div className="flex gap-1.5">
                        <button
                            type="button"
                            onClick={() => setScanning(true)}
                            disabled={!canScan}
                            className="border border-white/25 bg-white/10 px-3 py-2 text-[11px] font-semibold text-white disabled:opacity-40"
                        >
                            {t.scanShort}
                        </button>
                        {items.length > 0 && (
                            <button
                                type="button"
                                onClick={requestAccess}
                                className="border border-white/25 bg-white/10 px-3 py-2 text-[11px] font-semibold text-white"
                            >
                                {t.importShort}
                            </button>
                        )}
                    </div>
                </div>

                <div className="relative z-10 flex min-h-0 flex-1 items-center justify-center px-4 pb-4">
                    {active ? (
                        <div className="relative h-full w-full overflow-hidden border border-white/20 bg-white">
                            <LocalAssetPreview file={active.file} mode="hero" />
                            <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 bg-gradient-to-t from-emerald-950/80 to-transparent p-3">
                                <div className="min-w-0">
                                    <p className="truncate text-[12px] font-medium text-white">{active.file.name}</p>
                                    <p className="mt-0.5 text-[10px] text-white/75">
                                        {formatSize(active.file.size)}
                                        {' · '}
                                        {active.status === 'reading'
                                            ? t.reading
                                            : active.pages
                                                ? t.pages(active.pages)
                                                : '—'}
                                    </p>
                                </div>
                                <div className="flex gap-1.5">
                                    <button
                                        type="button"
                                        onClick={() => toggleSelect(active.id)}
                                        className={`px-2.5 py-1.5 text-[10px] font-semibold ${
                                            active.selected
                                                ? 'bg-white text-primary'
                                                : 'border border-white/30 text-white'
                                        }`}
                                    >
                                        {active.selected ? t.deselect : t.select}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => removeItem(active.id)}
                                        className="border border-white/30 px-2.5 py-1.5 text-[10px] font-semibold text-white"
                                    >
                                        {t.remove}
                                    </button>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="flex h-full w-full flex-col items-center justify-center border border-dashed border-white/30 bg-white/10 px-6 text-center">
                            <p className="text-sm font-medium text-white">{t.scanButton}</p>
                            <p className="mt-1 text-[11px] text-white/70">{t.scanHint}</p>
                            <button
                                type="button"
                                onClick={() => setScanning(true)}
                                disabled={!canScan}
                                className="mt-4 h-12 w-full max-w-xs bg-white text-[14px] font-semibold text-primary disabled:opacity-40"
                            >
                                {t.scanButton}
                            </button>
                        </div>
                    )}
                </div>
            </section>

            <section className="flex min-h-[46dvh] flex-1 flex-col bg-[#eef7f2]">
                <div className="flex items-center justify-between border-b border-emerald-900/10 bg-white px-4 py-2.5">
                    <div>
                        <p className="text-[12px] font-semibold tracking-tight text-ink">{t.galleryTitle}</p>
                        <p className="text-[10px] text-slate">{t.previewHint}</p>
                    </div>
                    {items.length > 0 && (
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setScanning(true)}
                                disabled={!canScan}
                                className="text-[11px] font-semibold text-primary disabled:opacity-40"
                            >
                                {t.scanShort}
                            </button>
                            <button
                                type="button"
                                onClick={requestAccess}
                                className="text-[11px] font-semibold text-primary"
                            >
                                + {t.importShort}
                            </button>
                        </div>
                    )}
                </div>

                {(notice || serverErrors.length > 0) && (
                    <div className="border-b border-amber-200/80 bg-amber-50 px-4 py-2 text-[11px] font-medium text-amber-900">
                        {notice}
                        {serverErrors.map((error) => (
                            <p key={error}>{error}</p>
                        ))}
                    </div>
                )}

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2">
                    {items.length === 0 ? (
                        <div className="flex h-full min-h-[220px] flex-col items-center justify-center border border-dashed border-emerald-300 bg-white px-6 py-8 text-center">
                            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-primary">
                                <svg viewBox="0 0 24 24" className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="1.8">
                                    <path d="M4 8.5V19a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V8.5" strokeLinecap="round" />
                                    <path d="M12 15V4" strokeLinecap="round" />
                                    <path d="M8.5 7.5 12 4l3.5 3.5" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </div>
                            <p className="text-[14px] font-semibold text-ink">{t.scanButton}</p>
                            <p className="mt-1 max-w-[16rem] text-[12px] leading-relaxed text-slate">{t.scanHint}</p>
                            <button
                                type="button"
                                onClick={() => setScanning(true)}
                                disabled={!canScan}
                                className="mt-5 h-12 w-full max-w-xs bg-primary text-[13px] font-semibold text-white disabled:opacity-40"
                            >
                                {t.scanButton}
                            </button>
                            <button
                                type="button"
                                onClick={requestAccess}
                                className="mt-2 h-12 w-full max-w-xs border border-primary text-[13px] font-semibold text-primary"
                            >
                                {t.grantAccess}
                            </button>
                            <p className="mt-3 text-[10px] font-medium text-primary/80">{t.formats}</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-3 gap-1.5">
                            <button
                                type="button"
                                onClick={requestAccess}
                                className="flex aspect-square flex-col items-center justify-center border border-dashed border-emerald-300 bg-white text-primary"
                            >
                                <span className="text-2xl leading-none">+</span>
                                <span className="mt-1 text-[10px] font-semibold">{t.importShort}</span>
                            </button>
                            {items.map((item) => (
                                <div
                                    key={item.id}
                                    className={`relative aspect-square overflow-hidden bg-white ${
                                        active?.id === item.id
                                            ? 'ring-2 ring-primary ring-offset-1 ring-offset-[#eef7f2]'
                                            : 'border border-emerald-900/10'
                                    }`}
                                >
                                    <button
                                        type="button"
                                        onClick={() => focusItem(item.id)}
                                        className="absolute inset-0"
                                        aria-label={item.file.name}
                                    >
                                        <LocalAssetPreview file={item.file} mode="grid" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-pressed={item.selected}
                                        onClick={() => toggleSelect(item.id)}
                                        className={`absolute start-1.5 top-1.5 z-10 flex h-6 w-6 items-center justify-center border text-[11px] font-bold ${
                                            item.selected
                                                ? 'border-primary bg-primary text-white'
                                                : 'border-white bg-white/90 text-transparent shadow'
                                        }`}
                                    >
                                        {item.selected ? indexSelect(selected, item.id) : ''}
                                    </button>
                                    {item.status === 'reading' && (
                                        <span className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-emerald-950/25">
                                            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </section>

            {sheet && (
                <AccessSheet
                    locale={locale}
                    mode={sheet}
                    onClose={() => setSheet(null)}
                    onContinue={openPicker}
                />
            )}
            {scanning && (
                <DocumentScanner
                    locale={locale}
                    copy={t}
                    room={Math.max(0, limits.max_files - items.length)}
                    onClose={() => setScanning(false)}
                    onPage={acceptFiles}
                />
            )}
        </PhoneShell>
    );
}

function indexSelect(selected, id) {
    const index = selected.findIndex((item) => item.id === id);
    return index >= 0 ? index + 1 : '';
}
