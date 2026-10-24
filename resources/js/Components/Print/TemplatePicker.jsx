import { copy } from '../../lib/printCopy';

export default function TemplatePicker({
    locale,
    templates,
    templatePrice,
    busyId,
    onClose,
    onPick,
}) {
    const t = copy[locale] || copy.ar;
    const titleKey = locale === 'ar' ? 'title_ar' : 'title_fr';

    return (
        <div className="fixed inset-0 z-50 flex justify-center bg-emerald-950/40">
            <div
                dir={locale === 'ar' ? 'rtl' : 'ltr'}
                className="flex h-full w-full max-w-md flex-col bg-[#f4faf7]"
            >
                <div className="flex shrink-0 items-center gap-2 border-b border-emerald-900/10 bg-white px-3 py-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex h-9 w-9 items-center justify-center text-ink"
                        aria-label={t.templatesBack}
                    >
                        <svg viewBox="0 0 24 24" className="h-5 w-5 rtl:scale-x-[-1]" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M15 6l-6 6 6 6" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    </button>
                    <div className="min-w-0">
                        <p className="truncate text-[16px] font-extrabold text-ink">{t.templatesTitle}</p>
                        <p className="text-[11px] font-semibold text-primary">{templatePrice} DA / {t.payUnitShort}</p>
                    </div>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3">
                    <ul className="space-y-2">
                        {templates.map((item) => {
                            const amount = item.pages * templatePrice;
                            const pending = busyId === item.id;

                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        disabled={busyId !== null}
                                        onClick={() => onPick(item.id)}
                                        className="flex w-full items-center gap-3 rounded-2xl border border-emerald-900/10 bg-white px-3 py-3 text-start disabled:opacity-60"
                                    >
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-primary">
                                            <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8">
                                                <path d="M7 3.5h7.5L19 8v12.5a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-16a1 1 0 0 1 1-1z" strokeLinejoin="round" />
                                                <path d="M14 3.5V8h5" strokeLinejoin="round" />
                                            </svg>
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-[14px] font-bold leading-snug text-ink">{item[titleKey]}</span>
                                            <span className="mt-0.5 block text-[11px] font-semibold text-slate">
                                                {t.pages(item.pages)} · {amount} DA
                                            </span>
                                        </span>
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-primary">
                                            {pending ? (
                                                <span className="h-4 w-4 animate-spin rounded-full border-2 border-emerald-200 border-t-primary" />
                                            ) : (
                                                <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="1.8">
                                                    <path d="M7 9V4h10v5" strokeLinejoin="round" />
                                                    <path d="M7 17H5.5A1.5 1.5 0 0 1 4 15.5v-5A1.5 1.5 0 0 1 5.5 9h13A1.5 1.5 0 0 1 20 10.5v5a1.5 1.5 0 0 1-1.5 1.5H17" />
                                                    <path d="M7 14h10v6H7z" />
                                                </svg>
                                            )}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            </div>
        </div>
    );
}
