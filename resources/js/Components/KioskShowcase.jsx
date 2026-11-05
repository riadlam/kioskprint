import { motion } from 'framer-motion';
import AnimateIn from './AnimateIn';
import { ArrowRight, Check } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

const kioskSrc = '/images/kiosk-real.png';

export default function KioskShowcase() {
    const { t, isAr } = useLandingLocale();
    const callouts = [
        { key: 'screen', className: 'top-[14%] end-[4%] sm:end-[8%]' },
        { key: 'coins', className: 'top-[28%] end-0 sm:end-[2%]' },
        { key: 'output', className: 'top-[42%] start-[6%] sm:start-[10%]' },
    ];

    return (
        <section className="section-padding section-y bg-white border-t border-gray-200 overflow-hidden">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 sm:gap-12 lg:gap-16 items-center">
                <AnimateIn direction="left" className="order-2 lg:order-1">
                    <p className="eyebrow mb-3">{t.showcase.eyebrow}</p>
                    <h2 className="heading-section mb-5">{t.showcase.title}</h2>
                    <p className="text-body mb-8 max-w-lg">{t.showcase.body}</p>
                    <ul className="space-y-4 mb-9">
                        {t.showcase.bullets.map((item) => (
                            <li key={item} className="flex items-start gap-3">
                                <span className="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <Check />
                                </span>
                                <span className="text-body text-ink">{item}</span>
                            </li>
                        ))}
                    </ul>
                    <a className="btn-secondary w-full sm:w-auto justify-center" href="#how-it-works">
                        {t.showcase.cta} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                    </a>
                </AnimateIn>

                <AnimateIn direction="right" className="relative flex justify-center w-full order-1 lg:order-2">
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 -z-10 m-auto h-[65%] w-[75%] rounded-full bg-primary/10 blur-[90px]"
                    />
                    <div className="relative w-full max-w-[280px] sm:max-w-[360px] md:max-w-[420px]">
                        <div
                            aria-hidden="true"
                            className="absolute inset-x-[10%] bottom-[2%] h-[10%] rounded-[100%] bg-ink/15 blur-xl"
                        />
                        <motion.img
                            alt={t.showcase.alt}
                            src={kioskSrc}
                            width={682}
                            height={1024}
                            className="relative z-10 w-full h-auto drop-shadow-[0_24px_48px_rgba(6,78,59,0.25)]"
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, amount: 0.35 }}
                            transition={{ duration: 0.6 }}
                            loading="lazy"
                            decoding="async"
                        />
                        {callouts.map((item, i) => (
                            <motion.div
                                key={item.key}
                                aria-hidden="true"
                                className={`absolute z-20 hidden sm:flex items-center gap-2 rounded-full border border-white/80 bg-white/95 px-3 py-1.5 text-sm font-semibold text-ink shadow-md backdrop-blur ${item.className}`}
                                initial={{ opacity: 0, scale: 0.9 }}
                                whileInView={{ opacity: 1, scale: 1 }}
                                viewport={{ once: true }}
                                transition={{ delay: 0.25 + i * 0.1, duration: 0.4 }}
                            >
                                <span className="h-2 w-2 rounded-full bg-primary shrink-0" />
                                {t.showcase.callouts[item.key]}
                            </motion.div>
                        ))}
                    </div>
                </AnimateIn>
            </div>
        </section>
    );
}
