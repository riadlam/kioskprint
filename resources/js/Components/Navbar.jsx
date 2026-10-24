import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import Logo from './Logo';
import { ArrowRight, CloseIcon, MenuIcon } from './Icons';

const links = [
    { href: '#home', label: 'Home' },
    { href: '#about', label: 'About' },
    { href: '#how-it-works', label: 'How It Works' },
    { href: '#partner', label: 'Partner' },
    { href: '#blog', label: 'Blog' },
    { href: '#contact', label: 'Contact' },
];

export default function Navbar() {
    const [open, setOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);

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
                    <div className="hidden lg:flex items-center gap-10">
                        {links.map((link, i) => (
                            <a
                                key={link.href}
                                href={link.href}
                                className={`text-[13px] uppercase tracking-wider font-medium transition-colors duration-200 ${
                                    i === 0 ? 'text-primary' : 'text-slate hover:text-ink'
                                }`}
                            >
                                {link.label}
                            </a>
                        ))}
                        <a
                            href="#print"
                            className="btn-primary !py-2 !px-5 !text-[13px] uppercase tracking-wider"
                        >
                            Print Now
                        </a>
                    </div>
                    <button
                        type="button"
                        className="lg:hidden p-2 -mr-2 text-ink"
                        aria-label={open ? 'Close menu' : 'Open menu'}
                        onClick={() => setOpen(!open)}
                    >
                        {open ? <CloseIcon /> : <MenuIcon />}
                    </button>
                </nav>
            </motion.header>
            {open && (
                <div className="fixed inset-0 z-40 lg:hidden bg-white pt-24 px-6 pb-8 flex flex-col gap-4">
                    {links.map((link) => (
                        <a
                            key={link.href}
                            href={link.href}
                            className="text-lg font-semibold text-ink py-2 border-b border-gray-100"
                            onClick={() => setOpen(false)}
                        >
                            {link.label}
                        </a>
                    ))}
                    <a href="#print" className="btn-primary mt-4 justify-center" onClick={() => setOpen(false)}>
                        Print Now <ArrowRight />
                    </a>
                </div>
            )}
        </>
    );
}
