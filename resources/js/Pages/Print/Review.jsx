import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import A4PagePreview from '../../Components/Print/A4PagePreview';
import AccessSheet from '../../Components/Print/AccessSheet';
import DuplexIcon from '../../Components/Print/DuplexIcon';
import PageThumb from '../../Components/Print/PageThumb';
import PhoneShell from '../../Components/Print/PhoneShell';
import SimplexIcon from '../../Components/Print/SimplexIcon';
import TemplatePicker from '../../Components/Print/TemplatePicker';
import { copy } from '../../lib/printCopy';
import { clearDraft, mimeOf, pagesFromDraft, readDraft, saveDraft } from '../../lib/localPrintDraft';
import { ACCEPT, countPdfPages, isAndroid, isPdfFile, isSupported, isTooBig, makeItemId, pickPrintFiles } from '../../lib/printFiles';

const ACCESS_KEY = 'metbati.file-access';
const LOCALE_KEY = 'metbati.print.locale';

function withIndex(list) {
    return list.map((page, index) => ({ ...page, index: index + 1 }));
}

export default function Review({ job, limits, templates = [], templatePrice = 50 }) {
    const fileInputRef = useRef(null);
    const autoPickRef = useRef(false);
    const dragIdRef = useRef(null);
    const pointerStartRef = useRef(null);
    const didDragRef = useRef(false);
    const pagesRef = useRef([]);

    const [locale, setLocale] = useState(() => sessionStorage.getItem(LOCALE_KEY) || job.locale || 'ar');
    const [sheet, setSheet] = useState(null);
    const [notice, setNotice] = useState('');
    const [focus, setFocus] = useState(null);
    const [duplex, setDuplex] = useState(() => Boolean(job.duplex));
    const serverBacked = (job.pages || []).length > 0;
    const [pages, setPages] = useState(() => withIndex(job.pages || []));
    const [uploadPercent, setUploadPercent] = useState(null);
    const [draggingId, setDraggingId] = useState(null);
    const [checkingOut, setCheckingOut] = useState(false);
    const [templatesOpen, setTemplatesOpen] = useState(false);
    const [templateBusy, setTemplateBusy] = useState(null);
    const form = useForm({ locale: job.locale, files: [] });
    const t = copy[locale];

    useEffect(() => {
        setDuplex(Boolean(job.duplex));
    }, [job.duplex]);

    useEffect(() => {
        if (!serverBacked) {
            return undefined;
        }
        const next = withIndex(job.pages || []);
        pagesRef.current = next;
        setPages(next);
        return undefined;
    }, [job.pages, serverBacked]);

    useEffect(() => {
        if (serverBacked) {
            return undefined;
        }
        let cancel = false;
        readDraft(job.id).then((draft) => {
            if (cancel || !draft?.pages?.length) {
                return;
            }
            setDuplex(Boolean(draft.duplex));
            const next = pagesFromDraft(draft);
            pagesRef.current = next;
            setPages(next);
        });
        return () => {
            cancel = true;
        };
    }, [job.id, serverBacked]);

    useEffect(() => {
        pagesRef.current = pages;
    }, [pages]);

    const active = focus
        ? pages.find((page) => page.id === focus)
        : pages[0] || null;

    function changeLocale(next) {
        sessionStorage.setItem(LOCALE_KEY, next);
        setLocale(next);
    }

    function requestPick() {
        if (isAndroid() && !sessionStorage.getItem(ACCESS_KEY)) {
            setSheet('ask');
            return;
        }
        openPicker();
    }

    useEffect(() => {
        if (pages.length > 0 || autoPickRef.current || form.processing) {
            return;
        }
        autoPickRef.current = true;
        const timer = window.setTimeout(() => requestPick(), 250);
        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function openPicker() {
        sessionStorage.setItem(ACCESS_KEY, '1');
        setSheet(null);
        const result = await pickPrintFiles(fileInputRef.current);
        if (result.permission === 'denied') {
            sessionStorage.removeItem(ACCESS_KEY);
            setSheet('denied');
            return;
        }

        const accepted = result.files.filter((file) => isSupported(file) && !isTooBig(file));
        if (!accepted.length) {
            if (result.files.length) {
                setNotice(result.files.some((file) => isTooBig(file)) ? t.tooBig : t.unsupported);
            }
            return;
        }

        setNotice('');
        if (serverBacked) {
            form.transform((data) => ({ ...data, locale, files: accepted }));
            form.post(`/print/${job.id}/documents`, { forceFormData: true, preserveScroll: true });
            return;
        }

        const draft = (await readDraft(job.id)) || { duplex, files: [], pages: [] };
        const additions = [];
        for (const file of accepted) {
            let pageCount = 1;
            if (isPdfFile(file)) {
                try {
                    pageCount = await countPdfPages(file);
                } catch {
                    pageCount = 1;
                }
            }
            const key = makeItemId();
            const type = mimeOf(file);
            draft.files.push({ key, name: file.name, type, pages: pageCount, file });
            for (let number = 1; number <= pageCount; number += 1) {
                additions.push({ id: `${key}-${number}`, fileKey: key, number });
            }
        }
        draft.pages = [...draft.pages, ...additions];
        draft.duplex = duplex;
        await saveDraft(job.id, draft);
        const next = pagesFromDraft(draft);
        pagesRef.current = next;
        setPages(next);
        setFocus(additions[0]?.id || null);
    }

    function removePage(pageId) {
        if (focus === pageId) {
            setFocus(null);
        }
        if (serverBacked) {
            router.delete(`/print/${job.id}/pages/${pageId}`, { preserveScroll: true });
            return;
        }

        setPages((current) => {
            const next = withIndex(current.filter((page) => page.id !== pageId));
            const kept = new Set(next.map((page) => page.fileKey));
            readDraft(job.id).then((draft) => {
                if (!draft) {
                    return;
                }
                draft.pages = draft.pages.filter((page) => page.id !== pageId);
                draft.files = draft.files.filter((file) => kept.has(file.key));
                draft.duplex = duplex;
                saveDraft(job.id, draft);
            });
            pagesRef.current = next;
            return next;
        });
    }

    function setDuplexMode(next) {
        setDuplex(next);
        if (!serverBacked) {
            readDraft(job.id).then((draft) => {
                if (!draft) {
                    return;
                }
                draft.duplex = next;
                saveDraft(job.id, draft);
            });
        }
    }

    function movePage(fromId, toId) {
        if (!fromId || !toId || fromId === toId) {
            return;
        }

        setPages((current) => {
            const from = current.findIndex((page) => page.id === fromId);
            const to = current.findIndex((page) => page.id === toId);
            if (from < 0 || to < 0) {
                return current;
            }
            const next = [...current];
            const [item] = next.splice(from, 1);
            next.splice(to, 0, item);
            const ordered = withIndex(next);
            if (!serverBacked) {
                readDraft(job.id).then((draft) => {
                    if (!draft) {
                        return;
                    }
                    const byId = new Map(draft.pages.map((page) => [page.id, page]));
                    draft.pages = ordered.map((page) => byId.get(page.id)).filter(Boolean);
                    draft.duplex = duplex;
                    saveDraft(job.id, draft);
                });
            }
            return ordered;
        });
    }

    function chooseTemplate(id) {
        if (templateBusy) {
            return;
        }
        setTemplateBusy(id);
        router.post('/print/templates', { template: id, locale }, {
            onFinish: () => setTemplateBusy(null),
        });
    }

    function goToPay() {
        if (pages.length < 1 || checkingOut) {
            return;
        }

        setCheckingOut(true);
        if (serverBacked) {
            router.post(
                `/print/${job.id}/checkout`,
                {
                    page_ids: pagesRef.current.map((page) => page.id),
                    duplex,
                },
                {
                    onFinish: () => setCheckingOut(false),
                },
            );
            return;
        }

        const current = pagesRef.current;

        readDraft(job.id).then((draft) => {
            const byKey = new Map((draft?.files || []).map((file) => [file.key, file]));
            const uploadFiles = [];
            const indexByKey = new Map();
            const selection = [];
            current.forEach((page) => {
                const stored = byKey.get(page.fileKey);
                if (!stored) {
                    return;
                }
                if (!indexByKey.has(page.fileKey)) {
                    indexByKey.set(page.fileKey, uploadFiles.length);
                    uploadFiles.push(stored.file);
                }
                selection.push(`${indexByKey.get(page.fileKey)}:${page.number}`);
            });
            if (!uploadFiles.length) {
                setCheckingOut(false);
                return;
            }
            form.transform(() => ({
                locale,
                duplex,
                files: uploadFiles,
                selection,
            }));
            form.post(`/print/${job.id}/hold`, {
                forceFormData: true,
                onProgress: (progress) => setUploadPercent(progress?.percentage ?? null),
                onSuccess: () => clearDraft(job.id),
                onFinish: () => {
                    setCheckingOut(false);
                    setUploadPercent(null);
                },
            });
        });
    }

    function onCardPointerDown(event, pageId) {
        if (event.button != null && event.button !== 0) {
            return;
        }
        if (event.target.closest('[data-no-drag]')) {
            return;
        }

        dragIdRef.current = pageId;
        didDragRef.current = false;
        pointerStartRef.current = { x: event.clientX, y: event.clientY };
        event.currentTarget.setPointerCapture?.(event.pointerId);
    }

    function onCardPointerMove(event) {
        if (!dragIdRef.current || !pointerStartRef.current) {
            return;
        }

        const dx = event.clientX - pointerStartRef.current.x;
        const dy = event.clientY - pointerStartRef.current.y;
        if (!didDragRef.current && Math.hypot(dx, dy) < 8) {
            return;
        }

        didDragRef.current = true;
        setDraggingId(dragIdRef.current);

        const el = document.elementFromPoint(event.clientX, event.clientY);
        const card = el?.closest?.('[data-page-id]');
        const overId = card ? Number(card.getAttribute('data-page-id')) : null;
        if (overId) {
            movePage(dragIdRef.current, overId);
        }
    }

    function onCardPointerUp(event, pageId) {
        if (!dragIdRef.current) {
            return;
        }

        const wasDragging = didDragRef.current;
        dragIdRef.current = null;
        pointerStartRef.current = null;
        setDraggingId(null);
        event.currentTarget.releasePointerCapture?.(event.pointerId);

        if (!wasDragging) {
            setFocus(pageId);
        }
    }

    return (
        <PhoneShell
            locale={locale}
            onLocale={changeLocale}
            step={1}
            dense
            footer={(
                <div className="border-t border-emerald-900/10 bg-white px-3 py-2 pb-[max(0.55rem,env(safe-area-inset-bottom))]">
                    <div className="mb-1.5 flex items-center justify-between gap-2 text-[11px] font-medium text-slate">
                        <span>{t.pages(job.total_pages)}</span>
                        <button
                            type="button"
                            onClick={requestPick}
                            disabled={form.processing || job.documents.length >= limits.max_files}
                            className="font-semibold text-primary disabled:text-emerald-300"
                        >
                            + {t.addFiles}
                        </button>
                    </div>

                    <div className="mb-2 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => setDuplexMode(false)}
                            className={`flex h-12 items-center justify-center gap-2 rounded-xl border px-2 transition ${
                                !duplex
                                    ? 'border-primary bg-emerald-50 text-primary shadow-sm'
                                    : 'border-emerald-900/10 bg-white text-slate'
                            }`}
                            aria-pressed={!duplex}
                        >
                            <SimplexIcon className="h-6 w-6 shrink-0" active={!duplex} />
                            <span className="text-[12px] font-extrabold leading-tight">{t.simplexLabel}</span>
                        </button>

                        <button
                            type="button"
                            onClick={() => setDuplexMode(true)}
                            className={`flex h-12 items-center justify-center gap-2 rounded-xl border px-2 transition ${
                                duplex
                                    ? 'border-primary bg-emerald-50 text-primary shadow-sm'
                                    : 'border-emerald-900/10 bg-white text-slate'
                            }`}
                            aria-pressed={duplex}
                        >
                            <DuplexIcon className="h-6 w-6 shrink-0" active={duplex} />
                            <span className="text-[12px] font-extrabold leading-tight">{t.duplexLabel}</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={goToPay}
                        disabled={pages.length < 1 || checkingOut}
                        className={`flex h-11 w-full items-center justify-center text-[13px] font-semibold text-white disabled:pointer-events-none ${
                            pages.length > 0 ? 'bg-primary' : 'bg-emerald-100 text-emerald-400'
                        }`}
                    >
                        {checkingOut ? `${t.uploading}${uploadPercent != null ? ` ${uploadPercent}%` : ''}` : t.payCta}
                    </button>
                </div>
            )}
        >
            <Head title={t.reviewTitle} />

            <input
                ref={fileInputRef}
                type="file"
                multiple
                accept={ACCEPT}
                className="sr-only"
                tabIndex={-1}
            />

            <section className="relative flex h-[34dvh] min-h-[200px] shrink-0 flex-col bg-primary">
                <div className="pointer-events-none absolute inset-0 print-studio-grid opacity-40" />

                <div className="relative z-10 flex items-center gap-2 px-3 py-1.5">
                    <p className="min-w-0 flex-1 truncate text-[12px] font-semibold text-white">
                        {active
                            ? `${t.pageOf(active.number, active.document.page_count)} · A4`
                            : t.reviewTitle}
                    </p>
                    <span className="font-mono text-[12px] font-semibold text-white/90">{pages.length}</span>
                    {active && (
                        <button
                            type="button"
                            onClick={() => removePage(active.id)}
                            className="border border-white/30 px-2 py-1 text-[10px] font-semibold text-white"
                        >
                            {t.remove}
                        </button>
                    )}
                </div>

                <div className="relative z-10 min-h-0 flex-1">
                    {active ? (
                        <A4PagePreview
                            document={active.document}
                            pageNumber={active.number}
                            locale={locale}
                        />
                    ) : (
                        <div className="absolute inset-2 flex items-center justify-center border border-dashed border-white/30 text-center">
                            <p className="text-[12px] font-medium text-white">{t.emptyTitle}</p>
                        </div>
                    )}
                </div>
            </section>

            <section className="flex min-h-0 flex-1 flex-col bg-[#eef7f2]">
                <div className="flex shrink-0 items-center justify-between gap-2 border-b border-emerald-900/10 bg-white px-3 py-1.5">
                    <p className="min-w-0 truncate text-[11px] font-semibold text-ink">{t.galleryTitle || t.reviewTitle}</p>
                    <button
                        type="button"
                        onClick={() => setTemplatesOpen(true)}
                        className="shrink-0 border border-primary/30 bg-emerald-50 px-2 py-1 text-[10px] font-bold text-primary"
                    >
                        {t.templatesButton}
                    </button>
                </div>

                {(notice || Object.values(form.errors).length > 0) && (
                    <div className="shrink-0 border-b border-amber-200/80 bg-amber-50 px-3 py-1.5 text-[11px] font-medium text-amber-900">
                        {notice}
                        {Object.values(form.errors).map((error) => (
                            <p key={error}>{error}</p>
                        ))}
                    </div>
                )}

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-1.5">
                    {pages.length === 0 ? (
                        <button
                            type="button"
                            onClick={requestPick}
                            disabled={form.processing}
                            className="flex h-full min-h-[180px] w-full flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-primary/40 bg-white/70 px-4 py-6 text-center transition active:scale-[0.99] disabled:opacity-60"
                        >
                            <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-primary">
                                <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden>
                                    <path
                                        d="M12 16V7m0 0l-3.5 3.5M12 7l3.5 3.5"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                    <path
                                        d="M5 16.5V18a2 2 0 002 2h10a2 2 0 002-2v-1.5"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </span>
                            <span className="text-[15px] font-extrabold text-ink">
                                {form.processing ? t.uploading : t.uploadFiles}
                            </span>
                            <span className="max-w-[220px] text-[12px] font-medium leading-snug text-slate">
                                {t.uploadFilesHint}
                            </span>
                        </button>
                    ) : (
                        <div className="grid grid-cols-4 gap-1">
                            {pages.map((page) => {
                                const isActive = active?.id === page.id;
                                const isDragging = draggingId === page.id;

                                return (
                                    <div
                                        key={page.id}
                                        data-page-id={page.id}
                                        onPointerDown={(event) => onCardPointerDown(event, page.id)}
                                        onPointerMove={onCardPointerMove}
                                        onPointerUp={(event) => onCardPointerUp(event, page.id)}
                                        onPointerCancel={(event) => onCardPointerUp(event, page.id)}
                                        className={`relative aspect-[210/297] touch-none overflow-hidden bg-white ${
                                            isDragging
                                                ? 'z-20 scale-[1.03] opacity-90 shadow-lg ring-2 ring-primary'
                                                : isActive
                                                    ? 'ring-2 ring-primary ring-offset-1 ring-offset-[#eef7f2]'
                                                    : 'border border-emerald-900/10'
                                        }`}
                                    >
                                        <div className="absolute inset-0">
                                            <PageThumb
                                                document={page.document}
                                                pageNumber={page.number}
                                                locale={locale}
                                            />
                                        </div>
                                        <span className="pointer-events-none absolute start-1 top-1 bg-primary px-1 py-0.5 font-mono text-[9px] font-semibold text-white">
                                            {page.index}
                                        </span>
                                        <span className="pointer-events-none absolute bottom-1 start-1 flex h-4 w-4 items-center justify-center rounded bg-ink/70 text-white">
                                            <svg className="h-2.5 w-2.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden>
                                                <circle cx="5" cy="4" r="1.2" />
                                                <circle cx="11" cy="4" r="1.2" />
                                                <circle cx="5" cy="8" r="1.2" />
                                                <circle cx="11" cy="8" r="1.2" />
                                                <circle cx="5" cy="12" r="1.2" />
                                                <circle cx="11" cy="12" r="1.2" />
                                            </svg>
                                        </span>
                                        <button
                                            type="button"
                                            data-no-drag
                                            aria-label={t.remove}
                                            onClick={(event) => {
                                                event.stopPropagation();
                                                removePage(page.id);
                                            }}
                                            className="absolute end-1 top-1 z-10 flex h-5 w-5 items-center justify-center bg-white/95 text-[12px] leading-none text-ink shadow"
                                        >
                                            ×
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </section>

            {templatesOpen && (
                <TemplatePicker
                    locale={locale}
                    templates={templates}
                    templatePrice={templatePrice}
                    busyId={templateBusy}
                    onClose={() => setTemplatesOpen(false)}
                    onPick={chooseTemplate}
                />
            )}

            {sheet && (
                <AccessSheet
                    locale={locale}
                    mode={sheet}
                    onClose={() => setSheet(null)}
                    onContinue={openPicker}
                />
            )}
        </PhoneShell>
    );
}
