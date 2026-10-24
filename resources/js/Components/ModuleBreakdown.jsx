import { useState } from 'react';
import AnimateIn from './AnimateIn';

const modules = [
    'Touchscreen display',
    'Payment terminal',
    'Structural frame',
    'Print engine',
    'Secure side panel',
    'Enclosed housing',
];

export default function ModuleBreakdown() {
    const [position, setPosition] = useState(50);

    const handleDrag = (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const update = (clientX) => {
            const pct = ((clientX - rect.left) / rect.width) * 100;
            setPosition(Math.min(95, Math.max(5, pct)));
        };
        update(e.clientX);
        const onMove = (ev) => update(ev.clientX);
        const onUp = () => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        };
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
    };

    return (
        <section className="section-padding section-y bg-gray-50 border-t border-gray-200">
            <AnimateIn>
                <div className="text-center max-w-2xl mx-auto mb-8">
                    <p className="eyebrow mb-3">Precision engineering</p>
                    <h2 className="heading-section mb-5">Built module by module</h2>
                    <p className="text-body">
                        Every Metbati kiosk is engineered as independent, serviceable modules (display, payment
                        terminal, print engine, and enclosed housing) for fast maintenance and industrial-grade
                        reliability.
                    </p>
                </div>
            </AnimateIn>
            <AnimateIn delay={0.1}>
                <div
                    className="rounded-lg border border-gray-200 bg-[#EAE4E0] px-6 py-8 md:px-10 md:py-16 shadow-ds-sm max-w-4xl mx-auto cursor-col-resize select-none"
                    onPointerDown={handleDrag}
                >
                    <div className="relative max-w-[1024px] mx-auto w-full aspect-[1024/439] overflow-hidden rounded-md">
                        {/* Base: blueprint / grayscale engineering view */}
                        <img
                            src="/images/kiosk-machine.png"
                            alt=""
                            className="absolute inset-0 w-full h-full object-contain grayscale opacity-80"
                        />
                        {/* Reveal: full-color recolored view */}
                        <div className="absolute inset-0 overflow-hidden" style={{ width: `${position}%` }}>
                            <img
                                src="/images/kiosk-machine.png"
                                alt="Metbati kiosk modules"
                                className="absolute inset-0 h-full object-contain recolor-green"
                                style={{ width: `${100 / (position / 100)}%`, maxWidth: 'none' }}
                            />
                        </div>
                        {/* Divider handle */}
                        <div
                            className="absolute top-0 bottom-0 w-0.5 bg-primary shadow-[0_0_12px_rgba(5,150,105,0.7)]"
                            style={{ left: `${position}%` }}
                        >
                            <span className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white text-xs shadow-md">
                                ⇆
                            </span>
                        </div>
                    </div>
                    <p className="text-center text-xs text-slate mt-4">Drag to explore the modules</p>
                </div>
            </AnimateIn>
            <AnimateIn delay={0.2}>
                <div className="flex flex-wrap justify-center gap-x-8 gap-y-3 mt-6 max-w-4xl mx-auto">
                    {modules.map((label) => (
                        <div key={label} className="flex items-center gap-2">
                            <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                            <span className="text-small text-slate">{label}</span>
                        </div>
                    ))}
                </div>
            </AnimateIn>
        </section>
    );
}
