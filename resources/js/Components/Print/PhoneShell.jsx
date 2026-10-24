import { copy } from '../../lib/printCopy';

export default function PhoneShell({
    locale,
    onLocale,
    step,
    children,
    footer,
    dense = false,
    chrome = true,
}) {
    const t = copy[locale] || copy.ar;

    return (
        <div
            dir={locale === 'ar' ? 'rtl' : 'ltr'}
            className={`print-studio text-ink ${dense ? 'h-dvh overflow-hidden' : 'min-h-dvh'}`}
        >
            <div className={`mx-auto flex w-full max-w-md flex-col ${dense ? 'h-full' : 'min-h-dvh'}`}>
                {chrome ? (
                    <header className="relative z-20 flex shrink-0 items-center justify-between border-b border-emerald-900/10 bg-white/95 px-3 py-2 backdrop-blur-md pt-[max(0.5rem,env(safe-area-inset-top))]">
                        <div className="flex items-center gap-2.5">
                            <p className="text-[12px] font-semibold tracking-[0.2em]">
                                <span className="text-ink">MET</span>
                                <span className="text-primary">BATI</span>
                            </p>
                            <span className="h-3 w-px bg-emerald-900/10" />
                            <p className="font-mono text-[10px] font-medium tracking-wide text-primary">{t.step(step)}</p>
                        </div>
                        <LangToggle locale={locale} onLocale={onLocale} />
                    </header>
                ) : null}
                <main className={dense ? 'flex min-h-0 flex-1 flex-col overflow-hidden' : 'flex-1'}>
                    {children}
                </main>
                {footer ? <div className="shrink-0">{footer}</div> : null}
            </div>
        </div>
    );
}

export function LangToggle({ locale, onLocale }) {
    return (
        <div className="flex items-center gap-0.5 border border-emerald-900/10 bg-emerald-50 p-0.5">
            {['ar', 'fr'].map((code) => (
                <button
                    key={code}
                    type="button"
                    onClick={() => onLocale(code)}
                    className={`min-w-9 px-2 py-1 text-[10px] font-semibold tracking-wide ${
                        locale === code ? 'bg-primary text-white' : 'text-slate'
                    }`}
                >
                    {code === 'ar' ? 'AR' : 'FR'}
                </button>
            ))}
        </div>
    );
}
