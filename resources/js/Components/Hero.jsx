import { motion } from 'framer-motion';
import AnimateIn from './AnimateIn';
import { ArrowRight } from './Icons';

const kioskSrc = '/images/kiosk-hero.png';

export default function Hero() {
    return (
        <section
            id="home"
            className="relative min-h-[100vh] flex items-start overflow-hidden bg-[#F6F4F0] border-b border-gray-200"
        >
            <div
                className="absolute top-1/2 right-0 -translate-y-1/2 w-[800px] h-[800px] rounded-full bg-white/60 blur-[120px] pointer-events-none"
                aria-hidden="true"
            />
            <div className="relative section-padding w-full pt-[88px] pb-16 md:pt-[96px] md:pb-24">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-8 items-center">
                    <div className="max-w-xl">
                        <AnimateIn>
                            <p className="inline-flex items-center gap-2 text-[11px] uppercase tracking-widest text-slate mb-8">
                                <span className="h-1.5 w-1.5 bg-primary rounded-full" />
                                Algeria&apos;s most trusted smart printing network
                            </p>
                        </AnimateIn>
                        <AnimateIn delay={0.05}>
                            <h1 className="font-semibold text-display md:text-display-lg lg:text-display-xl text-ink leading-[1.05] tracking-tight mb-6">
                                Print. Anytime.
                                <br />
                                <span className="text-primary italic font-medium pr-2">Anywhere.</span>
                            </h1>
                        </AnimateIn>
                        <AnimateIn delay={0.1}>
                            <p className="text-body text-slate mb-10 max-w-md leading-relaxed">
                                Algeria&apos;s first self-service printing kiosk. Walk up, scan, print, and collect with
                                no queues, no shopkeepers, and no fixed hours.
                            </p>
                        </AnimateIn>
                        <AnimateIn delay={0.15}>
                            <div className="flex flex-wrap gap-4">
                                <a className="btn-primary" href="#partner">
                                    Partner With Us <ArrowRight />
                                </a>
                                <a className="btn-secondary" href="#print" id="print">
                                    Print Now
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
                                    alt="Metbati self-service smart printing kiosk"
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
                                className="absolute z-20 top-12 -left-4 md:-left-12 flex flex-col justify-center rounded-md border border-gray-100 bg-white/90 px-4 py-3 backdrop-blur-md shadow-[0_12px_40px_rgb(0,0,0,0.06)]"
                                initial={{ opacity: 0, y: 12 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.5, duration: 0.5 }}
                            >
                                <p className="text-[14px] font-medium leading-none text-ink tracking-tight mb-1">
                                    24×7 Access
                                </p>
                                <p className="text-[10px] leading-none text-slate uppercase tracking-wider">Always on</p>
                            </motion.div>
                            <motion.div
                                aria-hidden="true"
                                className="absolute z-20 bottom-24 -right-2 md:-right-8 flex flex-col justify-center rounded-md border border-gray-100 bg-white/90 px-4 py-3 backdrop-blur-md shadow-[0_12px_40px_rgb(0,0,0,0.06)]"
                                initial={{ opacity: 0, y: 12 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.65, duration: 0.5 }}
                            >
                                <p className="text-[14px] font-medium leading-none text-ink tracking-tight mb-1">
                                    &lt;60s
                                </p>
                                <p className="text-[10px] leading-none text-slate uppercase tracking-wider">
                                    Print to collect
                                </p>
                            </motion.div>
                        </motion.div>
                    </div>
                </div>
            </div>
        </section>
    );
}
