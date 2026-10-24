import { useEffect, useRef, useState } from 'react';
import AnimateIn from './AnimateIn';

function CountUp({ value, className }) {
    const ref = useRef(null);
    const [display, setDisplay] = useState(typeof value === 'number' ? 0 : value);
    const [started, setStarted] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const obs = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) setStarted(true);
            },
            { threshold: 0.3 },
        );
        obs.observe(el);
        return () => obs.disconnect();
    }, []);

    useEffect(() => {
        if (!started) return;
        if (typeof value !== 'number') {
            setDisplay(value);
            return;
        }
        const duration = 1400;
        const start = performance.now();
        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            setDisplay(Math.floor(value * eased));
            if (t < 1) requestAnimationFrame(tick);
            else setDisplay(value);
        };
        requestAnimationFrame(tick);
    }, [started, value]);

    return (
        <span ref={ref} className={className}>
            {display}
        </span>
    );
}

const stats = [
    {
        value: '24/7',
        label: 'Always available',
        sub: 'Every kiosk, every day',
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
            </svg>
        ),
    },
    {
        value: '<60s',
        label: 'Print to collect',
        sub: 'From scan to handoff',
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z" />
            </svg>
        ),
    },
    {
        value: '100%',
        label: 'Self-service',
        sub: 'No queues, no waiting',
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <path d="M12 3 20 7v6c0 5-3.5 7.5-8 8.5C7.5 20.5 4 18 4 13V7l8-4Z" />
                <path d="m9 12 2 2 4-4" />
            </svg>
        ),
    },
    {
        value: 1000,
        suffix: '+',
        label: 'Kiosks vision',
        sub: 'Nationwide by 2030',
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6" />
            </svg>
        ),
    },
];

export default function Stats() {
    return (
        <section className="relative section-padding section-y overflow-hidden bg-[#0B1220]">
            <div
                className="absolute inset-0 opacity-[0.14] bg-cover bg-center"
                style={{ backgroundImage: "url('/images/circuit-bg.webp')" }}
                aria-hidden="true"
            />
            <div
                className="absolute -top-24 left-1/2 -translate-x-1/2 w-[720px] h-[420px] rounded-full bg-primary/20 blur-[120px]"
                aria-hidden="true"
            />
            <div
                className="absolute bottom-0 right-0 w-[360px] h-[360px] rounded-full bg-emerald-500/10 blur-[100px]"
                aria-hidden="true"
            />

            <div className="relative max-w-6xl mx-auto">
                <AnimateIn>
                    <div className="text-center max-w-3xl mx-auto mb-14 md:mb-16">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-primary mb-4">
                            Built for Algeria
                        </p>
                        <h2 className="font-extrabold text-3xl md:text-5xl text-white leading-tight tracking-tight">
                            Making printing simple for{' '}
                            <span className="text-primary">everyone, everywhere.</span>
                        </h2>
                        <p className="mt-5 text-base md:text-lg text-white/55 max-w-2xl mx-auto leading-relaxed">
                            Fast, secure, and always on — Metbati kiosks put high-quality printing within reach across
                            campuses, offices, and public spaces.
                        </p>
                    </div>
                </AnimateIn>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
                    {stats.map((stat, i) => (
                        <AnimateIn key={stat.label} delay={i * 0.08}>
                            <div className="group relative h-full rounded-2xl border border-white/10 bg-white/[0.04] backdrop-blur-sm p-6 md:p-7 transition-all duration-300 hover:border-primary/40 hover:bg-white/[0.07]">
                                <div className="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/15 text-primary ring-1 ring-primary/25">
                                    {stat.icon}
                                </div>
                                <div className="flex items-baseline gap-0.5">
                                    <CountUp
                                        value={stat.value}
                                        className="font-extrabold text-4xl md:text-5xl text-white tracking-tight"
                                    />
                                    {stat.suffix && (
                                        <span className="font-extrabold text-4xl md:text-5xl text-primary">
                                            {stat.suffix}
                                        </span>
                                    )}
                                </div>
                                <p className="mt-3 text-base font-semibold text-white">{stat.label}</p>
                                <p className="mt-1 text-sm text-white/45">{stat.sub}</p>
                                <div className="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                            </div>
                        </AnimateIn>
                    ))}
                </div>
            </div>
        </section>
    );
}
