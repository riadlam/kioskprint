import AnimateIn from './AnimateIn';
import { ArrowRight, MaskIcon } from './Icons';

const features = [
    {
        title: 'Cloud Printing',
        description: 'Print from anywhere, anytime using our secure cloud infrastructure.',
        icon: '/assets/metbati/icons/03-cloud.png',
    },
    {
        title: 'AI Monitoring',
        description: 'Real-time monitoring and predictive alerts ensure zero downtime.',
        icon: '/assets/metbati/icons/10-ai-monitoring.png',
    },
    {
        title: 'Secure & Private',
        description: 'End-to-end encryption and secure data handling for every print.',
        icon: '/assets/metbati/icons/13-security.png',
    },
    {
        title: 'Smart Kiosks',
        description: 'Intuitive kiosks for quick print, scan, upload and secure collection.',
        icon: '/assets/metbati/icons/26-franchise.png',
    },
    {
        title: 'Cashless Payments',
        description: 'Cards, wallets and multiple cashless options for a seamless experience.',
        icon: '/assets/metbati/icons/05-payment.png',
    },
    {
        title: '24x7 Availability',
        description: 'Always available kiosks across locations for your convenience.',
        icon: '/assets/metbati/icons/29-24x7.png',
    },
];

export default function Features() {
    return (
        <section id="about" className="section-padding section-y bg-[#F8F9FA]">
            <AnimateIn>
                <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-12">
                    <div>
                        <p className="eyebrow mb-3 uppercase tracking-widest text-sm font-semibold text-slate">
                            01. FEATURE / BENTO CARDS
                        </p>
                        <h2 className="heading-section max-w-xl">
                            Everything you need.
                            <br />
                            One <span className="text-primary">smart solution.</span>
                        </h2>
                    </div>
                    <a className="btn-text shrink-0" href="#how-it-works">
                        Explore all features <ArrowRight size={16} />
                    </a>
                </div>
            </AnimateIn>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {features.map((feature, index) => (
                    <AnimateIn key={feature.title} delay={index * 0.1}>
                        <div className="h-full bg-white rounded-[24px] p-8 shadow-sm border border-gray-100 flex flex-col items-start transition-all hover:shadow-md hover:-translate-y-1 duration-300">
                            <div className="w-16 h-16 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center mb-8">
                                <MaskIcon src={feature.icon} size={32} />
                            </div>
                            <h3 className="font-bold text-lg text-ink mb-3">{feature.title}</h3>
                            <p className="text-sm text-slate leading-relaxed">{feature.description}</p>
                        </div>
                    </AnimateIn>
                ))}
            </div>
        </section>
    );
}
