import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import Logo from './Logo';
import { ArrowRight, CloseIcon, MenuIcon } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

export default function Navbar() {
    const { t, locale, setLocale, isAr } = useLandingLocale();
    const [open, setOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);

    const links = [
        { href: '#home', label: t.nav.home },
        { href: '#about', label: t.nav.about },
        { href: '#how-it-works', label: t.nav.how },
        { href: '#partner', label: t.nav.partner },
        { href: '#blog', label: t.nav.blog },
        { href: '#contact', label: t.nav.contact },
    ];

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 24);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <>
            <motion.header
                initial={{ y: -80, opacity: 0 }}
                animate={{ y: 0, opacity: 1 }}
                transition={{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }}
                className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${
                    scrolled ? 'bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-200/60' : 'bg-transparent'
                }`}
            >
                <nav className="section-padding flex items-center justify-between h-[72px]">
                    <a href="#home" aria-label="Metbati home">
                        <Logo />
                    </a>
                    <div className="hidden lg:flex items-center gap-8">
                        {links.map((link, i) => (
                            <a
                                key={link.href}
                                href={link.href}
                                className={`text-[15px] uppercase tracking-wider font-medium transition-colors duration-200 ${
                                    i === 0 ? 'text-primary' : 'text-slate hover:text-ink'
                                }`}
                            >
                                {link.label}
                            </a>
                        ))}
                        <div className="flex items-center rounded-full border border-black/10 bg-white/80 p-0.5 text-sm font-semibold">
                            <button
                                type="button"
                                onClick={() => setLocale('ar')}
                                className={`rounded-full px-3 py-1.5 ${locale === 'ar' ? 'bg-ink text-white' : 'text-slate'}`}
                            >
                                {t.lang.ar}
                            </button>
                            <button
                                type="button"
                                onClick={() => setLocale('en')}
                                className={`rounded-full px-3 py-1.5 ${locale === 'en' ? 'bg-ink text-white' : 'text-slate'}`}
                            >
                                {t.lang.en}
                            </button>
                        </div>
                        <a href="#print" className="btn-primary !py-2.5 !px-5 !text-[15px] uppercase tracking-wider">
                            {t.nav.printNow}
                        </a>
                    </div>
                    <div className="flex items-center gap-2 lg:hidden">
                        <div className="flex items-center rounded-full border border-black/10 bg-white/80 p-0.5 text-sm font-semibold">
                            <button
                                type="button"
                                onClick={() => setLocale('ar')}
                                className={`rounded-full px-2 py-1 ${locale === 'ar' ? 'bg-ink text-white' : 'text-slate'}`}
                            >
                                ع
                            </button>
                            <button
                                type="button"
                                onClick={() => setLocale('en')}
                                className={`rounded-full px-2 py-1 ${locale === 'en' ? 'bg-ink text-white' : 'text-slate'}`}
                            >
                                EN
                            </button>
                        </div>
                        <button
                            type="button"
                            className="p-2 -me-2 text-ink"
                            aria-label={open ? t.nav.closeMenu : t.nav.openMenu}
                            onClick={() => setOpen(!open)}
                        >
                            {open ? <CloseIcon /> : <MenuIcon />}
                        </button>
                    </div>
                </nav>
            </motion.header>
            {open && (
                <div className="fixed inset-0 z-40 lg:hidden bg-white pt-24 px-6 pb-8 flex flex-col gap-4">
                    {links.map((link) => (
                        <a
                            key={link.href}
                            href={link.href}
                            className="text-xl font-semibold text-ink py-2 border-b border-gray-100"
                            onClick={() => setOpen(false)}
                        >
                            {link.label}
                        </a>
                    ))}
                    <a href="#print" className="btn-primary mt-4 justify-center" onClick={() => setOpen(false)}>
                        {t.nav.printNow} <ArrowRight className={isAr ? 'rotate-180' : ''} />
                    </a>
                </div>
            )}
        </>
    );
}
