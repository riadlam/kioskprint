import { useEffect } from 'react';

export default function Modal({ open, onClose, title, subtitle, children, wide = false, danger = false }) {
    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKey = (event) => {
            if (event.key === 'Escape') {
                onClose?.();
            }
        };

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKey);

        return () => {
            document.body.style.overflow = previous;
            window.removeEventListener('keydown', onKey);
        };
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-[80] flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true">
            <button
                type="button"
                aria-label="إغلاق"
                className="absolute inset-0 bg-[#0d0f14]/55 backdrop-blur-[2px]"
                onClick={onClose}
            />
            <div
                className={`relative flex max-h-[min(92vh,880px)] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-2xl ${
                    wide ? 'sm:max-w-2xl' : 'sm:max-w-lg'
                }`}
            >
                <div className={`shrink-0 border-b px-5 py-4 sm:px-6 ${danger ? 'border-red-100 bg-red-50/60' : 'border-black/5'}`}>
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <h2 className={`text-[17px] font-bold tracking-tight ${danger ? 'text-red-700' : 'text-ink'}`}>
                                {title}
                            </h2>
                            {subtitle && <p className="mt-1 text-[13px] leading-relaxed text-slate">{subtitle}</p>}
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate transition hover:bg-black/5 hover:text-ink"
                            aria-label="إغلاق"
                        >
                            <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6 sm:py-6">{children}</div>
            </div>
        </div>
    );
}
