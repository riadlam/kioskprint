export default function PrintBusyOverlay({ label }) {
    return (
        <div
            className="fixed inset-0 z-[200] flex items-center justify-center bg-[#0d0f14]/55 px-6 backdrop-blur-[2px]"
            role="alert"
            aria-busy="true"
            aria-live="assertive"
            onClick={(event) => event.stopPropagation()}
            onPointerDown={(event) => event.stopPropagation()}
        >
            <div className="flex w-full max-w-[220px] flex-col items-center gap-4 rounded-2xl bg-white px-6 py-7 shadow-xl">
                <span className="metbati-loader" aria-hidden />
                <p className="text-center text-[13px] font-semibold leading-snug text-ink">
                    {label}
                </p>
            </div>
        </div>
    );
}
