import { copy } from '../../lib/printCopy';

export default function AccessSheet({ locale, mode, onContinue, onClose }) {
    const t = copy[locale];
    const denied = mode === 'denied';

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-emerald-950/45 p-0 sm:items-center sm:p-4">
            <div className="w-full max-w-md border-t border-emerald-900/10 bg-white sm:border">
                <div className="border-b border-emerald-900/10 bg-emerald-50 px-5 py-4">
                    <p className="font-mono text-[10px] uppercase tracking-[0.22em] text-primary">Metbati</p>
                    <h2 className="mt-2 text-lg font-semibold tracking-tight text-ink">
                        {denied ? t.androidDeniedTitle : t.androidTitle}
                    </h2>
                </div>
                <div className="px-5 py-4">
                    <p className="text-sm leading-relaxed text-slate">
                        {denied ? t.androidDenied : t.androidBody}
                    </p>
                    <div className="mt-5 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="h-11 border border-emerald-900/10 text-sm font-medium text-slate"
                        >
                            {t.cancel}
                        </button>
                        <button
                            type="button"
                            onClick={onContinue}
                            className="h-11 bg-primary text-sm font-semibold text-white"
                        >
                            {denied ? t.tryAgain : t.androidContinue}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
