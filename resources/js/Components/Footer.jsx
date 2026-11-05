import AnimateIn from './AnimateIn';
import Logo from './Logo';
import { ArrowRight } from './Icons';
import { useLandingLocale } from '../lib/LandingLocale';

export default function Footer() {
    const { t, isAr } = useLandingLocale();

    return (
        <footer id="contact" className="bg-ink text-white">
            <div className="section-padding py-16 md:py-20">
                <div className="grid grid-cols-1 md:grid-cols-12 gap-12 md:gap-8">
                    <AnimateIn className="md:col-span-4">
                        <Logo light />
                        <p className="text-[15px] text-white/60 mt-6 max-w-xs leading-relaxed">{t.footer.blurb}</p>
                    </AnimateIn>
                    <AnimateIn delay={0.05} className="md:col-span-2">
                        <h3 className="text-caption uppercase tracking-widest text-white/40 mb-4">{t.footer.company}</h3>
                        <ul className="space-y-3">
                            {t.footer.companyLinks.map((link) => (
                                <li key={link.label}>
                                    <a className="text-[15px] text-white/70 hover:text-primary transition-colors" href={link.href}>
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </AnimateIn>
                    <AnimateIn delay={0.1} className="md:col-span-2">
                        <h3 className="text-caption uppercase tracking-widest text-white/40 mb-4">{t.footer.solutions}</h3>
                        <ul className="space-y-3">
                            {t.footer.solutionLinks.map((link) => (
                                <li key={link.label}>
                                    <a className="text-[15px] text-white/70 hover:text-primary transition-colors" href={link.href}>
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </AnimateIn>
                    <AnimateIn delay={0.15} className="md:col-span-2">
                        <h3 className="text-caption uppercase tracking-widest text-white/40 mb-4">{t.footer.resources}</h3>
                        <ul className="space-y-3">
                            {t.footer.resourceLinks.map((link) => (
                                <li key={link.label}>
                                    <a className="text-[15px] text-white/70 hover:text-primary transition-colors" href={link.href}>
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </AnimateIn>
                    <AnimateIn delay={0.2} className="md:col-span-2">
                        <h3 className="text-caption uppercase tracking-widest text-white/40 mb-4">{t.footer.help}</h3>
                        <a
                            href="mailto:contact@metbati.com"
                            className="text-[15px] text-white/70 hover:text-primary transition-colors mb-3 block"
                        >
                            contact@metbati.com
                        </a>
                        <p className="text-[15px] text-white/60 leading-relaxed mb-5">{t.footer.city}</p>
                        <a className="btn-primary !py-2.5 !px-5 !text-small" href="#contact">
                            {t.footer.find} <ArrowRight size={15} className={isAr ? 'rotate-180' : ''} />
                        </a>
                    </AnimateIn>
                </div>
                <div className="mt-16 pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
                    <p className="text-small text-white/40">
                        © {new Date().getFullYear()} Metbati. {t.footer.rights}
                    </p>
                    <p className="text-small text-white/40">{t.footer.made}</p>
                </div>
            </div>
        </footer>
    );
}
