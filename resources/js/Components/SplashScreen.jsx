import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { KioskMark } from './Logo';
import { useLandingLocale } from '../lib/LandingLocale';
import { BRAND_AR, BRAND_EN } from '../lib/landingCopy';

export default function SplashScreen() {
    const { isAr } = useLandingLocale();
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const t = setTimeout(() => setVisible(false), 2200);
        return () => clearTimeout(t);
    }, []);

    const brand = isAr ? BRAND_AR : BRAND_EN;
    const letters = Array.from(brand);
    const accentFrom = isAr ? Math.ceil(letters.length / 2) : 3;

    return (
        <AnimatePresence>
            {visible && (
                <motion.div
                    className="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-[#E5E9E7] overflow-hidden"
                    initial={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.5 }}
                >
                    <div className="flex flex-col items-center gap-8 px-6">
                        <motion.div
                            initial={{ opacity: 0, scale: 0, rotate: -15 }}
                            animate={{ opacity: 1, scale: 1, rotate: 0 }}
                            transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
                        >
                            <KioskMark size={88} />
                        </motion.div>
                        <div className="flex" dir={isAr ? 'rtl' : 'ltr'}>
                            {letters.map((char, i) => (
                                <motion.span
                                    key={`${char}-${i}`}
                                    className={`inline-block font-extrabold text-3xl sm:text-4xl md:text-5xl tracking-tight ${
                                        i >= accentFrom ? 'text-primary' : 'text-ink'
                                    }`}
                                    initial={{ opacity: 0, y: 40, scale: 0.8 }}
                                    animate={{ opacity: 1, y: 0, scale: 1 }}
                                    transition={{ delay: 0.3 + i * 0.05, duration: 0.4 }}
                                >
                                    {char}
                                </motion.span>
                            ))}
                        </div>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
