import { useEffect, useState } from 'react';

export default function PayErrorDialog({ code, copy }) {
    const [open, setOpen] = useState(Boolean(code));
    const message = copy?.payError?.[code] || (code ? copy?.payError?.not_completed : null);

    useEffect(() => {
        setOpen(Boolean(code));
    }, [code]);

    if (!open || !message) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-[#101513]/45 px-6">
            <div className="w-full max-w-[320px] rounded-3xl bg-white px-6 py-8 text-center shadow-2xl" style={{ animation: 'metbati-pop 360ms ease-out' }}>
                <style>{`@keyframes metbati-pop { from { transform: scale(.82); opacity: 0 } to { transform: scale(1); opacity: 1 } }`}</style>
                <span className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <svg viewBox="0 0 24 24" className="h-10 w-10" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden>
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 8v5" strokeLinecap="round" />
                        <path d="M12 16.5h.01" strokeLinecap="round" />
                    </svg>
                </span>
                <p className="mt-5 text-[1.25rem] font-black text-ink">{message.title}</p>
                <p className="mt-2 text-[13px] font-medium leading-5 text-slate">{message.body}</p>
                <button
                    type="button"
                    onClick={() => setOpen(false)}
                    className="mt-5 inline-flex h-12 w-full items-center justify-center rounded-2xl bg-primary text-[15px] font-black text-white"
                >
                    {copy.payErrorOk}
                </button>
            </div>
        </div>
    );
}
