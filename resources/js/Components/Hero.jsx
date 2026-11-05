import { motion } from 'framer-motion';
import AnimateIn from './AnimateIn';
import { ArrowRight } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

const kioskSrc = '/images/kiosk-real.png';

export default function Hero() {
    const { t, isAr } = useLandingLocale();

    return (
        <section
            id="home"
            className="relative min-h-[100svh] flex items-start overflow-hidden bg-[#F6F4F0] border-b border-gray-200"
        >
            <div
                className="absolute top-[18%] end-[-10%] w-[min(720px,95vw)] h-[min(720px,95vw)] rounded-full bg-primary/10 blur-[110px] pointer-events-none"
                aria-hidden="true"
            />
            <div
                className="absolute bottom-[-10%] start-[-15%] w-[min(480px,80vw)] h-[min(480px,80vw)] rounded-full bg-emerald-200/30 blur-[100px] pointer-events-none"
                aria-hidden="true"
            />

            <div className="relative section-padding w-full pt-[84px] pb-12 sm:pt-[88px] sm:pb-16 md:pt-[96px] md:pb-24">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 sm:gap-12 lg:gap-10 items-center">
                    <div className="max-w-xl mx-auto lg:mx-0 text-center lg:text-start w-full order-2 lg:order-1">
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
                            <div className="flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4 justify-center lg:justify-start">
                                <a className="btn-primary w-full sm:w-auto" href="#partner">
                                    {t.hero.partner} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                                </a>
                                <a className="btn-secondary w-full sm:w-auto" href="#print" id="print">
                                    {t.hero.printNow}
                                </a>
                            </div>
                        </AnimateIn>
                    </div>

                    <div className="relative flex justify-center lg:justify-end order-1 lg:order-2 px-1 sm:px-0">
                        <motion.div
                            className="relative w-full max-w-[210px] sm:max-w-[240px] md:max-w-[280px] lg:max-w-[320px]"
                            initial={{ opacity: 0, y: 28, scale: 0.96 }}
                            animate={{ opacity: 1, y: 0, scale: 1 }}
                            transition={{ duration: 0.85, delay: 0.15, ease: [0.22, 1, 0.36, 1] }}
                        >
                            <div
                                aria-hidden="true"
                                className="absolute -inset-6 sm:-inset-10 rounded-[40%] bg-primary/12 blur-3xl"
                            />

                            <motion.div
                                className="relative z-10 animate-float"
                                style={{ animationDuration: '7s' }}
                            >
                                <img
                                    alt={t.hero.kioskAlt}
                                    className="relative z-10 w-full h-auto select-none drop-shadow-[0_18px_36px_rgba(6,78,59,0.22)]"
                                    src={kioskSrc}
                                    width={474}
                                    height={991}
                                    decoding="async"
                                    fetchPriority="high"
                                />
                            </motion.div>

                            <motion.div
                                aria-hidden="true"
                                className="absolute z-20 top-[10%] -start-1 sm:-start-6 md:-start-10 rounded-2xl border border-white/80 bg-white/90 px-3 py-2.5 sm:px-4 sm:py-3 backdrop-blur-md shadow-[0_12px_40px_rgba(0,0,0,0.08)]"
                                initial={{ opacity: 0, x: isAr ? 16 : -16 }}
                                animate={{ opacity: 1, x: 0 }}
                                transition={{ delay: 0.55, duration: 0.5 }}
                            >
                                <p className="text-sm sm:text-base font-semibold leading-none text-ink tracking-tight mb-1">
                                    {t.hero.badgeAccess}
                                </p>
                                <p className="text-[11px] sm:text-xs leading-none text-slate uppercase tracking-wider">
                                    {t.hero.badgeAccessSub}
                                </p>
                            </motion.div>

                            <motion.div
                                aria-hidden="true"
                                className="absolute z-20 bottom-[18%] -end-1 sm:-end-5 md:-end-8 rounded-2xl border border-white/80 bg-white/90 px-3 py-2.5 sm:px-4 sm:py-3 backdrop-blur-md shadow-[0_12px_40px_rgba(0,0,0,0.08)]"
                                initial={{ opacity: 0, x: isAr ? -16 : 16 }}
                                animate={{ opacity: 1, x: 0 }}
                                transition={{ delay: 0.7, duration: 0.5 }}
                            >
                                <p className="text-sm sm:text-base font-semibold leading-none text-ink tracking-tight mb-1">
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
