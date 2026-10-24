import { useState } from 'react';
import AnimateIn from './AnimateIn';

const tags = [
    {
        label: 'New Features',
        icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
        ),
    },
    {
        label: 'Offers & Discounts',
        icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                <path d="m9 12 2 2 4-4" />
            </svg>
        ),
    },
    {
        label: 'Success Stories',
        icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" />
            </svg>
        ),
    },
    {
        label: 'Industry Insights',
        icon: (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M3 3v18h18" />
                <path d="m19 9-5 5-4-4-3 3" />
            </svg>
        ),
    },
];

export default function Newsletter() {
    const [email, setEmail] = useState('');
    const [sent, setSent] = useState(false);

    const onSubmit = (e) => {
        e.preventDefault();
        setSent(true);
    };

    return (
        <section id="blog" className="section-padding bg-white pb-20">
            <div className="w-full max-w-7xl mx-auto">
                <div className="pt-12">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center mb-12">
                        <AnimateIn>
                            <div className="text-center md:text-left">
                                <h2 className="text-3xl md:text-[34px] leading-tight font-bold text-ink mb-3">
                                    Stay Updated with Metbati
                                </h2>
                                <p className="text-slate text-[15px] mb-8">
                                    Get the latest updates, new features and offers straight to your inbox.
                                </p>
                                <form className="relative max-w-md mx-auto md:mx-0" onSubmit={onSubmit}>
                                    <input
                                        type="email"
                                        required
                                        placeholder="Enter your email address"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        className="w-full pl-6 pr-[130px] py-4 rounded-xl border border-gray-200 shadow-sm bg-white text-ink placeholder:text-gray-400 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    />
                                    <button
                                        type="submit"
                                        className="absolute right-2 top-2 bottom-2 px-6 rounded-lg bg-primary text-white font-medium hover:opacity-90 transition-colors shadow-sm"
                                    >
                                        {sent ? 'Thanks!' : 'Subscribe'}
                                    </button>
                                </form>
                            </div>
                        </AnimateIn>
                        <AnimateIn delay={0.1} className="flex justify-center md:justify-end">
                            <img
                                alt="Newsletter envelope"
                                src="/images/newsletter.png"
                                width={400}
                                height={300}
                                className="w-full max-w-[320px] h-auto object-contain drop-shadow-2xl recolor-green"
                                onError={(e) => {
                                    e.currentTarget.style.display = 'none';
                                }}
                            />
                        </AnimateIn>
                    </div>
                    <AnimateIn delay={0.15}>
                        <div className="flex flex-wrap items-center justify-center md:justify-between gap-6 pt-10 border-t border-gray-200/60">
                            {tags.map((tag) => (
                                <div key={tag.label} className="flex items-center gap-3">
                                    <span className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        {tag.icon}
                                    </span>
                                    <span className="text-[13px] font-semibold text-ink">{tag.label}</span>
                                </div>
                            ))}
                        </div>
                    </AnimateIn>
                </div>
            </div>
        </section>
    );
}
