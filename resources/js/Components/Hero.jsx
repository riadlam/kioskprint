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
            className="relative h-[100svh] overflow-hidden bg-[#F6F4F0] border-b border-gray-200"
        >
            <div
                className="absolute top-[18%] end-[-10%] w-[min(720px,95vw)] h-[min(720px,95vw)] rounded-full bg-primary/10 blur-[110px] pointer-events-none"
                aria-hidden="true"
            />
            <div
                className="absolute bottom-[-10%] start-[-15%] w-[min(480px,80vw)] h-[min(480px,80vw)] rounded-full bg-emerald-200/30 blur-[100px] pointer-events-none"
                aria-hidden="true"
            />

            <div className="relative section-padding flex h-full min-h-0 flex-col pt-[72px] pb-4 sm:pt-[80px] sm:pb-6">
                <div className="grid h-full min-h-0 grid-cols-1 items-center gap-3 lg:grid-cols-[1.15fr_0.85fr] lg:gap-8">
                    <div className="mx-auto w-full max-w-xl text-center lg:mx-0 lg:text-start order-2 lg:order-1">
                        <AnimateIn>
                            <p className="mb-2 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-primary sm:mb-3 sm:text-sm">
                                <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-primary" />
                                <span className="text-start">{t.hero.eyebrow}</span>
                            </p>
                        </AnimateIn>
                        <AnimateIn delay={0.05}>
                            <h1 className="font-extrabold leading-[1.12] tracking-tight text-ink text-[1.7rem] sm:text-[2.15rem] lg:text-[2.75rem] xl:text-[3.15rem]">
                                {t.hero.titleLine1}
                                <span className="mt-1 block text-primary">{t.hero.titleLine2}</span>
                            </h1>
                        </AnimateIn>
                        <AnimateIn delay={0.1}>
                            <p className="mx-auto mb-4 mt-3 max-w-md text-[15px] leading-snug text-slate sm:text-lg lg:mx-0">
                                {t.hero.body}
                            </p>
                        </AnimateIn>
                        <AnimateIn delay={0.15}>
                            <div className="flex flex-col justify-center gap-2 sm:flex-row sm:flex-wrap sm:gap-3 lg:justify-start">
                                <a className="btn-primary w-full !px-5 !py-2.5 !text-base sm:w-auto" href="#partner">
                                    {t.hero.partner} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                                </a>
                                <a className="btn-secondary w-full !px-5 !py-2.5 !text-base sm:w-auto" href="#print" id="print">
                                    {t.hero.printNow}
                                </a>
                            </div>
                        </AnimateIn>
                    </div>

                    <div className="relative flex h-[30svh] min-h-0 items-center justify-center order-1 lg:order-2 lg:h-full lg:justify-end">
                        <motion.div
                            className="relative h-full max-h-[30svh] w-auto lg:max-h-[min(70svh,560px)]"
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
                                    className="relative z-10 h-[30svh] w-auto max-w-[70vw] object-contain select-none drop-shadow-[0_18px_36px_rgba(6,78,59,0.22)] lg:h-[min(68svh,520px)]"
                                    src={kioskSrc}
                                    width={474}
                                    height={991}
                                    decoding="async"
                                    fetchPriority="high"
                                />
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
