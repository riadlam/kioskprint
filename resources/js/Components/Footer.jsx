import AnimateIn from './AnimateIn';
import Logo from './Logo';
import { ArrowRight } from './Icons';

const company = [
    { href: '#about', label: 'About Us' },
    { href: '#how-it-works', label: 'How It Works' },
    { href: '#partner', label: 'Partner With Us' },
    { href: '#contact', label: 'Contact Us' },
];

const solutions = [
    { href: '#partner', label: 'For Institutions' },
    { href: '#partner', label: 'For Businesses' },
    { href: '#how-it-works', label: 'Cloud Printing' },
    { href: '#how-it-works', label: 'Kiosk Network' },
];

const resources = [
    { href: '#blog', label: 'Blog' },
    { href: '#how-it-works', label: 'How It Works' },
    { href: '#', label: 'Privacy Policy' },
    { href: '#', label: 'Terms of Service' },
];

export default function Footer() {
    return (
        <footer id="contact" className="bg-ink text-white">
            <div className="section-padding py-16 md:py-20">
                <div className="grid grid-cols-1 md:grid-cols-12 gap-12 md:gap-8">
                    <AnimateIn className="md:col-span-4">
                        <Logo light />
                        <p className="text-[15px] text-white/60 mt-6 max-w-xs leading-relaxed">
                            Algeria&apos;s smart printing kiosk network. Building the future of self-service printing
                            infrastructure.
                        </p>
                    </AnimateIn>
                    <AnimateIn delay={0.05} className="md:col-span-2">
                        <h3 className="text-caption uppercase tracking-widest text-white/40 mb-4">Company</h3>
                        <ul className="space-y-3">
                            {company.map((link) => (
                                <li key={link.label}>
                                    <a className="text-[15px] text-white/70 hover:text-primary transition-colors" href={link.href}>
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </AnimateIn>
                    <AnimateIn delay={0.1} className="md:col-span-2">
                        <h3 className="text-caption uppercase tracking-widest text-white/40 mb-4">Solutions</h3>
                        <ul className="space-y-3">
                            {solutions.map((link) => (
                                <li key={link.label}>
                                    <a className="text-[15px] text-white/70 hover:text-primary transition-colors" href={link.href}>
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </AnimateIn>
                    <AnimateIn delay={0.15} className="md:col-span-2">
                        <h3 className="text-caption uppercase tracking-widest text-white/40 mb-4">Resources</h3>
                        <ul className="space-y-3">
                            {resources.map((link) => (
                                <li key={link.label}>
                                    <a className="text-[15px] text-white/70 hover:text-primary transition-colors" href={link.href}>
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </AnimateIn>
                    <AnimateIn delay={0.2} className="md:col-span-2">
                        <h3 className="text-caption uppercase tracking-widest text-white/40 mb-4">Need help?</h3>
                        <a
                            href="mailto:contact@metbati.com"
                            className="text-[15px] text-white/70 hover:text-primary transition-colors mb-3 block"
                        >
                            contact@metbati.com
                        </a>
                        <p className="text-[15px] text-white/60 leading-relaxed mb-5">
                            Algiers, Algeria
                        </p>
                        <a className="btn-primary !py-2.5 !px-5 !text-small" href="#contact">
                            Find a Kiosk <ArrowRight size={15} />
                        </a>
                    </AnimateIn>
                </div>
                <div className="mt-16 pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
                    <p className="text-small text-white/40">
                        © {new Date().getFullYear()} Metbati. All rights reserved.
                    </p>
                    <p className="text-small text-white/40">Made with care in Algeria</p>
                </div>
            </div>
        </footer>
    );
}
