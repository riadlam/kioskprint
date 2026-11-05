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
            className="relative min-h-[100svh] flex items-start overflow-hidden bg-[#F6F4F0] border-b border-gray-200"
        >
            <div
                className="absolute top-1/2 end-0 -translate-y-1/2 w-[min(800px,90vw)] h-[min(800px,90vw)] rounded-full bg-white/60 blur-[120px] pointer-events-none"
                aria-hidden="true"
            />
            <div className="relative section-padding w-full pt-[84px] pb-12 sm:pt-[88px] sm:pb-16 md:pt-[96px] md:pb-24">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 sm:gap-12 lg:gap-8 items-center">
                    <div className="max-w-xl mx-auto lg:mx-0 text-center lg:text-start w-full">
                        <AnimateIn>
                            <p className="inline-flex items-center gap-2 text-sm md:text-base uppercase tracking-widest text-slate mb-5 sm:mb-8">
                                <span className="h-1.5 w-1.5 bg-primary rounded-full shrink-0" />
                                <span className="text-start">{t.hero.eyebrow}</span>
                            </p>
                        </AnimateIn>
                        <AnimateIn delay={0.05}>
                            <h1 className="font-semibold text-display md:text-display-lg lg:text-display-xl text-ink leading-[1.15] tracking-tight mb-5 sm:mb-6">
                                {t.hero.titleLine1}
                                <br />
                                <span className={`text-primary font-medium pe-2 ${isAr ? '' : 'italic'}`}>
                                    {t.hero.titleLine2}
                                </span>
                            </h1>
                        </AnimateIn>
                        <AnimateIn delay={0.1}>
                            <p className="text-body text-slate mb-8 sm:mb-10 max-w-md mx-auto lg:mx-0 leading-relaxed">
                                {t.hero.body}
                            </p>
                        </AnimateIn>
                        <AnimateIn delay={0.15}>
                            <div className="flex flex-col xs:flex-row sm:flex-row flex-wrap gap-3 sm:gap-4 justify-center lg:justify-start">
                                <a className="btn-primary w-full sm:w-auto" href="#partner">
                                    {t.hero.partner} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                                </a>
                                <a className="btn-secondary w-full sm:w-auto" href="#print" id="print">
                                    {t.hero.printNow}
                                </a>
                            </div>
                        </AnimateIn>
                    </div>
                    <div className="relative flex justify-center lg:justify-end px-2 sm:px-0">
                        <motion.div
                            className="relative w-full max-w-[280px] sm:max-w-[420px] md:max-w-[500px] lg:max-w-[650px] flex items-center justify-center"
                            initial={{ opacity: 0, scale: 0.96 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 0.8, delay: 0.2, ease: [0.22, 1, 0.36, 1] }}
                        >
                            <div className="relative z-10 w-full">
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
                                className="absolute z-20 top-6 start-0 sm:top-12 sm:-start-4 md:-start-12 flex flex-col justify-center rounded-md border border-gray-100 bg-white/90 px-3 py-2.5 sm:px-4 sm:py-3 backdrop-blur-md shadow-[0_12px_40px_rgb(0,0,0,0.06)]"
                                initial={{ opacity: 0, y: 12 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.5, duration: 0.5 }}
                            >
                                <p className="text-sm sm:text-base font-medium leading-none text-ink tracking-tight mb-1.5">
                                    {t.hero.badgeAccess}
                                </p>
                                <p className="text-[11px] sm:text-xs leading-none text-slate uppercase tracking-wider">
                                    {t.hero.badgeAccessSub}
                                </p>
                            </motion.div>
                            <motion.div
                                aria-hidden="true"
                                className="absolute z-20 bottom-16 end-0 sm:bottom-24 sm:-end-2 md:-end-8 flex flex-col justify-center rounded-md border border-gray-100 bg-white/90 px-3 py-2.5 sm:px-4 sm:py-3 backdrop-blur-md shadow-[0_12px_40px_rgb(0,0,0,0.06)]"
                                initial={{ opacity: 0, y: 12 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.65, duration: 0.5 }}
                            >
                                <p className="text-sm sm:text-base font-medium leading-none text-ink tracking-tight mb-1.5">
                                    {t.hero.badgeSpeed}
                                </p>
                                <p className="text-[11px] sm:text-xs leading-none text-slate uppercase tracking-wider">
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
