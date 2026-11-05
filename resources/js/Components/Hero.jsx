import { motion } from 'framer-motion';
import AnimateIn from './AnimateIn';
import { ArrowRight } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

const kioskSrc = '/images/kiosk-hero.png';

export default function Hero() {
    const { t, isAr } = useLandingLocale();

    return (
        <section
            id="home"
            className="relative min-h-[100vh] flex items-start overflow-hidden bg-[#F6F4F0] border-b border-gray-200"
        >
            <div
                className="absolute top-1/2 end-0 -translate-y-1/2 w-[800px] h-[800px] rounded-full bg-white/60 blur-[120px] pointer-events-none"
                aria-hidden="true"
            />
            <div className="relative section-padding w-full pt-[88px] pb-16 md:pt-[96px] md:pb-24">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-8 items-center">
                    <div className="max-w-xl">
                        <AnimateIn>
                            <p className="inline-flex items-center gap-2 text-sm md:text-base uppercase tracking-widest text-slate mb-8">
                                <span className="h-1.5 w-1.5 bg-primary rounded-full" />
                                {t.hero.eyebrow}
                            </p>
                        </AnimateIn>
                        <AnimateIn delay={0.05}>
                            <h1 className="font-semibold text-display md:text-display-lg lg:text-display-xl text-ink leading-[1.15] tracking-tight mb-6">
                                {t.hero.titleLine1}
                                <br />
                                <span className={`text-primary font-medium pe-2 ${isAr ? '' : 'italic'}`}>
                                    {t.hero.titleLine2}
                                </span>
                            </h1>
                        </AnimateIn>
                        <AnimateIn delay={0.1}>
                            <p className="text-body text-slate mb-10 max-w-md leading-relaxed">{t.hero.body}</p>
                        </AnimateIn>
                        <AnimateIn delay={0.15}>
                            <div className="flex flex-wrap gap-4">
                                <a className="btn-primary" href="#partner">
                                    {t.hero.partner} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                                </a>
                                <a className="btn-secondary" href="#print" id="print">
                                    {t.hero.printNow}
                                </a>
                            </div>
                        </AnimateIn>
                    </div>
                    <div className="relative flex justify-center lg:justify-end">
                        <motion.div
                            className="relative w-full max-w-[500px] lg:max-w-[650px] flex items-center justify-center"
                            initial={{ opacity: 0, scale: 0.96 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 0.8, delay: 0.2, ease: [0.22, 1, 0.36, 1] }}
                        >
                            <div className="relative z-10">
                                <img
                                    alt={t.hero.kioskAlt}
                                    className="w-full h-auto select-none drop-shadow-2xl recolor-green"
                                    src={kioskSrc}
                                    width={1122}
                                    height={1402}
                                    onError={(e) => {
                                        e.currentTarget.src = '/images/kiosk-fallback.svg';
                                    }}
                                />
                            </div>
                            <motion.div
                                aria-hidden="true"
                                className="absolute z-20 top-12 -start-4 md:-start-12 flex flex-col justify-center rounded-md border border-gray-100 bg-white/90 px-4 py-3 backdrop-blur-md shadow-[0_12px_40px_rgb(0,0,0,0.06)]"
                                initial={{ opacity: 0, y: 12 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.5, duration: 0.5 }}
                            >
                                <p className="text-base font-medium leading-none text-ink tracking-tight mb-1.5">
                                    {t.hero.badgeAccess}
                                </p>
                                <p className="text-xs leading-none text-slate uppercase tracking-wider">
                                    {t.hero.badgeAccessSub}
                                </p>
                            </motion.div>
                            <motion.div
                                aria-hidden="true"
                                className="absolute z-20 bottom-24 -end-2 md:-end-8 flex flex-col justify-center rounded-md border border-gray-100 bg-white/90 px-4 py-3 backdrop-blur-md shadow-[0_12px_40px_rgb(0,0,0,0.06)]"
                                initial={{ opacity: 0, y: 12 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.65, duration: 0.5 }}
                            >
                                <p className="text-base font-medium leading-none text-ink tracking-tight mb-1.5">
                                    {t.hero.badgeSpeed}
                                </p>
                                <p className="text-xs leading-none text-slate uppercase tracking-wider">
                                    {t.hero.badgeSpeedSub}
                                </p>
                            </motion.div>
                        </motion.div>
                    </div>
                </div>
            </div>
        </section>
    );
}
